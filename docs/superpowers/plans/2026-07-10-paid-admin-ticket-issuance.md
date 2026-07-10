# Paid Admin Ticket Issuance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace complimentary admin ticket creation with voucher or OTP-authorized personal-wallet settlement while automatically linking web admins to one mobile identity, Triumphant ID, and wallet.

**Architecture:** Provision one `MobileUser` and `GoshenWallet` per normalized-email identity, then protect browser wallet spending with a hashed, request-bound, single-use email challenge. Preserve the legacy `PaymentInstallment` row only as one canonical full-payment record, hard-disable payment-plan entry points, guard every Goshen payment path against partial or multi-row settlement, and let the admin issuance service settle the complete listed price by voucher or the issuer's wallet.

**Tech Stack:** Laravel 12, Filament 5, Eloquent/MySQL, PHPUnit, Spatie Permission, existing event-payment package, existing dynamic SMTP service.

## Global Constraints

- Implement only in `C:\Appbuild\Goshen-Laravel-Admin-Staging`; Flutter is a read-only compatibility reference for this change.
- Keep `goshen_ticket.issue` as create-only access assignable to any web admin role.
- Never create a complimentary, waived, zero-total, or zero-payment ticket through admin issuance.
- Wallet payment must debit only the authenticated web admin's same-email `MobileUser` wallet.
- Voucher and wallet settlement must create the normal booking, one canonical full-payment record, payment transaction, gateway-specific records, ticket, and audit trail.
- Goshen registrations allow one full payment only: no payment plan, deposit, scheduled balance, or partial settlement.
- Wallet top-ups, savings goals, and auto-top-up remain available but never create or reserve a partially paid registration.
- The legacy `PaymentInstallment` model/table may remain only as the single canonical payable row required by existing transaction foreign keys and gateway integrations.
- A normalized email maps to at most one `MobileUser`, one Triumphant ID, and one wallet.
- Browser wallet spending requires a six-digit numeric email code bound to the exact request, expiring after ten minutes, limited to five attempts, with a sixty-second resend cooldown and hourly send limit.
- Never persist or log plaintext voucher or OTP codes.
- Preserve unrelated untracked `.agents/` and `docs/superpowers/` files.
- Commit each independently verified task on the feature branch; after whole-branch review, merge and push `origin/main`, then deploy staging before production.

## File Map

- Create `app/Services/LinkedMobileAccountService.php`: resolves or creates the one mobile identity for a web admin.
- Modify `app/Providers/AppServiceProvider.php`: provisions linked identities and immediate wallets on model lifecycle events.
- Modify `app/Models/MobileUser.php`: exposes the one-to-one wallet relationship.
- Create `database/migrations/2026_07_10_160000_backfill_goshen_wallets_for_mobile_users.php`: idempotent existing-member wallet backfill.
- Create `app/Models/WebWalletVerificationChallenge.php`: persisted browser wallet challenge state.
- Create `app/Services/WebWalletVerificationService.php`: sends, rate-limits, verifies, audits, and consumes email codes.
- Create `database/migrations/2026_07_10_161000_create_web_wallet_verification_challenges_table.php`: challenge storage and indexes.
- Create `app/Services/GoshenAdminWalletPaymentService.php`: debits the issuer wallet and settles through `PaymentSettlementService`.
- Create `app/Services/GoshenSingleFullPaymentService.php`: creates and validates the one canonical full-payment record and blocks partial/multi-row settlement.
- Create `database/migrations/2026_07_10_162000_enforce_single_full_goshen_payments.php`: deactivates plans and safely normalizes only unambiguous unpaid legacy bookings.
- Modify `config/event-installments.php`: hard-disable generic payment-plan API and admin routes.
- Modify `database/seeders/GoshenRetreatDemoSeeder.php`: remove payment-plan creation and installment wording/permission grants.
- Modify active Goshen voucher, wallet, card-checkout, and offline settlement entry points to use the full-payment guard.
- Modify `app/Services/GoshenAdminTicketIssuanceService.php`: creates normal pending financial records and invokes voucher/wallet settlement.
- Modify `app/Filament/Resources/GoshenTicketResource.php`: paid payment-method fields and wallet-code controls.
- Modify `app/Filament/Resources/GoshenTicketResource/Pages/CreateGoshenTicket.php`: sends OTP and passes payment details into issuance.
- Create `tests/Feature/AccountWalletProvisioningTest.php`: linked identity, Triumphant ID, and wallet coverage.
- Modify `tests/Feature/MergedAccountCredentialTest.php`: uses the automatically linked mobile record instead of creating a duplicate email.
- Create `tests/Feature/WebWalletVerificationTest.php`: code security and rate-limit coverage.
- Rewrite `tests/Feature/GoshenAdminTicketIssuanceTest.php`: paid voucher/wallet, authorization, reporting, and failure coverage.

---

### Task 1: Linked Mobile Identity and Immediate Wallet Provisioning

**Files:**
- Create: `app/Services/LinkedMobileAccountService.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Models/MobileUser.php`
- Create: `database/migrations/2026_07_10_160000_backfill_goshen_wallets_for_mobile_users.php`
- Create: `tests/Feature/AccountWalletProvisioningTest.php`
- Modify: `tests/Feature/MergedAccountCredentialTest.php`

**Interfaces:**
- Produces: `LinkedMobileAccountService::forAdmin(User $admin): ?MobileUser`
- Produces: every new active `MobileUser` has a `GoshenWallet` before its creation request completes.
- Consumed later by: wallet OTP and paid issuance services.

- [ ] **Step 1: Write failing linked-identity and wallet tests**

```php
public function test_new_web_admin_receives_one_linked_mobile_identity_triumphant_id_and_wallet(): void
{
    $admin = User::query()->create([
        'name' => 'Ticket Admin',
        'email' => 'TICKET.ADMIN@example.test',
        'password' => 'StrongPassw0rd!',
    ]);

    $mobile = MobileUser::query()
        ->whereRaw('LOWER(email) = ?', ['ticket.admin@example.test'])
        ->firstOrFail();

    $this->assertSame(1, MobileUser::query()->whereRaw('LOWER(email) = ?', ['ticket.admin@example.test'])->count());
    $this->assertNotNull($mobile->triumphant_id);
    $this->assertTrue(Hash::check('StrongPassw0rd!', $mobile->password));
    $this->assertDatabaseHas('goshen_wallets', ['mobile_user_id' => $mobile->id, 'balance' => 0]);
    $this->assertSame($mobile->id, app(LinkedMobileAccountService::class)->forAdmin($admin)?->id);
}

public function test_new_mobile_user_receives_wallet_immediately_and_provisioning_is_idempotent(): void
{
    $mobile = MobileUser::query()->create([
        'name' => 'Member One',
        'email' => 'member.one@example.test',
        'password' => 'StrongPassw0rd!',
        'is_verified' => true,
        'email_verified_at' => now(),
        'is_blocked' => false,
        'is_deleted' => false,
    ]);

    app(GoshenWalletService::class)->walletFor($mobile);
    app(GoshenWalletService::class)->walletFor($mobile);

    $this->assertSame(1, GoshenWallet::query()->where('mobile_user_id', $mobile->id)->count());
}
```

- [ ] **Step 2: Run the tests and verify the current lazy-provisioning behaviour fails**

Run: `vendor\bin\phpunit.bat tests\Feature\AccountWalletProvisioningTest.php`

Expected: FAIL because web-user creation does not create a mobile record and mobile-user creation does not immediately create a wallet.

- [ ] **Step 3: Implement normalized-email account linking**

```php
final class LinkedMobileAccountService
{
    public function forAdmin(User $admin): ?MobileUser
    {
        $email = strtolower(trim((string) $admin->email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || blank($admin->password)) {
            return null;
        }

        return DB::transaction(function () use ($admin, $email): MobileUser {
            $mobile = MobileUser::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->lockForUpdate()
                ->first();

            if (! $mobile) {
                $mobile = MobileUser::query()->create([
                    'name' => $admin->name,
                    'email' => $email,
                    'password' => $admin->password,
                    'login_type' => 'linked_web_admin',
                    'is_verified' => true,
                    'email_verified_at' => now(),
                    'is_blocked' => false,
                    'is_deleted' => false,
                ]);
            }

            return $mobile;
        });
    }
}
```

Modify the model lifecycle handlers as follows while retaining the existing Triumphant ID, referral, and recursion-guard code around these additions:

```php
MobileUser::created(function (MobileUser $user): void {
    $this->syncMergedAdminCredentialsFromMobile($user, created: true);
    if (Schema::hasColumn('mobile_users', 'triumphant_id')) {
        app(TriumphantIdService::class)->assignFor($user);
    }

    if (Schema::hasTable('goshen_wallets')) {
        app(GoshenWalletService::class)->walletFor($user);
    }

    if (Schema::hasTable('goshen_referral_codes')) {
        app(GoshenReferralService::class)->ensureCodeFor($user);
    }
});

User::saved(function (User $user): void {
    $credentials = app(MergedAccountCredentialService::class);
    if ($credentials->isSyncing() || (! $user->wasRecentlyCreated && ! $user->wasChanged(['email', 'password']))) {
        return;
    }

    $mobile = app(LinkedMobileAccountService::class)->forAdmin($user);
    if ($mobile) {
        $credentials->syncMobileFromAdmin($user, $mobile);
    }
});
```

Add the explicit model relationship used by payment code:

```php
public function wallet(): HasOne
{
    return $this->hasOne(GoshenWallet::class, 'mobile_user_id');
}
```

Update `MergedAccountCredentialTest` to retrieve the automatically created same-email `MobileUser`, then overwrite only the mobile password/verification fields needed by each merge scenario. Do not insert a second mobile row with that email.

- [ ] **Step 4: Add the idempotent wallet backfill migration**

```php
public function up(): void
{
    $currency = strtoupper((string) config('event-installments.currency', 'GBP'));

    DB::table('mobile_users')
        ->where('is_deleted', false)
        ->orderBy('id')
        ->select('id')
        ->chunkById(500, function ($users) use ($currency): void {
            $now = now();
            DB::table('goshen_wallets')->insertOrIgnore(
                $users->map(fn ($user): array => [
                    'mobile_user_id' => $user->id,
                    'currency' => $currency,
                    'balance' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
            );
        });
}

public function down(): void
{
    // Wallets may contain financial history and are intentionally preserved.
}
```

- [ ] **Step 5: Run focused identity tests**

Run: `vendor\bin\phpunit.bat tests\Feature\AccountWalletProvisioningTest.php tests\Feature\MergedAccountCredentialTest.php tests\Feature\TriumphantIdServiceTest.php`

Expected: PASS with no duplicate mobile email, Triumphant ID, or wallet.

- [ ] **Step 6: Commit and push**

```powershell
git add app/Services/LinkedMobileAccountService.php app/Providers/AppServiceProvider.php app/Models/MobileUser.php database/migrations/2026_07_10_160000_backfill_goshen_wallets_for_mobile_users.php tests/Feature/AccountWalletProvisioningTest.php tests/Feature/MergedAccountCredentialTest.php
git commit -m "Provision linked member wallets for admins"
git push origin main
```

### Task 2: Reusable Browser Wallet Email Verification

**Files:**
- Create: `app/Models/WebWalletVerificationChallenge.php`
- Create: `app/Services/WebWalletVerificationService.php`
- Create: `database/migrations/2026_07_10_161000_create_web_wallet_verification_challenges_table.php`
- Create: `tests/Feature/WebWalletVerificationTest.php`

**Interfaces:**
- Produces: `WebWalletVerificationService::issue(User $admin, MobileUser $payer, string $purpose, array $context, ?string $ip, ?string $userAgent): WebWalletVerificationChallenge`
- Produces: `WebWalletVerificationService::consume(WebWalletVerificationChallenge $challenge, User $admin, MobileUser $payer, string $purpose, array $context, string $code, ?string $ip, ?string $userAgent): WebWalletVerificationChallenge`
- Produces: `WebWalletVerificationService::fingerprint(array $context): string`
- Consumed later by: `GoshenAdminTicketIssuanceService` and the Filament create page.

- [ ] **Step 1: Write failing challenge tests**

```php
public function test_wallet_code_is_six_numeric_digits_hashed_bound_and_single_use(): void
{
    [$admin, $payer] = $this->linkedIdentity();
    $sentBody = null;
    $this->mock(DynamicSmtpMailer::class, function (MockInterface $mock) use (&$sentBody): void {
        $mock->shouldReceive('sendRaw')->once()->andReturnUsing(
            function (string $to, string $subject, string $body) use (&$sentBody): void {
                $sentBody = $body;
            },
        );
    });

    $context = ['recipient_id' => 22, 'event_id' => 3, 'ticket_type_id' => 9, 'amount' => '150.00', 'currency' => 'GBP'];
    $challenge = app(WebWalletVerificationService::class)->issue($admin, $payer, 'admin_ticket_issue', $context, '127.0.0.1', 'PHPUnit');
    preg_match('/\b(\d{6})\b/', (string) $sentBody, $matches);

    $this->assertNotEmpty($matches[1] ?? null);
    $this->assertNotSame($matches[1], $challenge->code_hash);
    $this->assertTrue(Hash::check($matches[1], $challenge->code_hash));

    app(WebWalletVerificationService::class)->consume($challenge, $admin, $payer, 'admin_ticket_issue', $context, $matches[1], '127.0.0.1', 'PHPUnit');
    $this->expectException(ValidationException::class);
    app(WebWalletVerificationService::class)->consume($challenge->fresh(), $admin, $payer, 'admin_ticket_issue', $context, $matches[1], '127.0.0.1', 'PHPUnit');
}
```

Add the following concrete cases using the same linked identity, SMTP capture, and context fixture:

```php
public function test_expired_or_changed_wallet_challenge_is_rejected(): void
{
    [$service, $admin, $payer, $context, $challenge, $code] = $this->issuedChallenge();
    $this->travel(11)->minutes();
    $this->expectException(ValidationException::class);
    $service->consume($challenge, $admin, $payer, 'admin_ticket_issue', $context, $code, '127.0.0.1', 'PHPUnit');
}

public function test_five_wrong_codes_lock_the_challenge(): void
{
    [$service, $admin, $payer, $context, $challenge, $code] = $this->issuedChallenge();
    $wrongCode = $code === '000000' ? '999999' : '000000';
    foreach (range(1, 5) as $attempt) {
        try {
            $service->consume($challenge->fresh(), $admin, $payer, 'admin_ticket_issue', $context, $wrongCode, '127.0.0.1', 'PHPUnit');
        } catch (ValidationException) {
        }
    }
    $this->assertSame('locked', $challenge->fresh()->status);
    $this->assertSame(5, $challenge->fresh()->attempts);
}

public function test_resend_cooldown_and_hourly_limit_are_enforced(): void
{
    [$service, $admin, $payer, $context] = $this->challengeFixture();
    $service->issue($admin, $payer, 'admin_ticket_issue', $context, '127.0.0.1', 'PHPUnit');
    try {
        $service->issue($admin, $payer, 'admin_ticket_issue', $context, '127.0.0.1', 'PHPUnit');
        $this->fail('Expected resend cooldown rejection.');
    } catch (ValidationException) {
        $this->assertDatabaseCount('web_wallet_verification_challenges', 1);
    }
    foreach (range(2, 5) as $send) {
        $this->travel(61)->seconds();
        $service->issue($admin, $payer, 'admin_ticket_issue', $context, '127.0.0.1', 'PHPUnit');
    }
    $this->travel(61)->seconds();
    $this->expectException(ValidationException::class);
    $service->issue($admin, $payer, 'admin_ticket_issue', $context, '127.0.0.1', 'PHPUnit');
}

public function test_smtp_failure_leaves_no_usable_wallet_challenge(): void
{
    [$service, $admin, $payer, $context] = $this->challengeFixture();
    $this->mock(DynamicSmtpMailer::class, fn (MockInterface $mock) => $mock
        ->shouldReceive('sendRaw')->once()->andThrow(new RuntimeException('SMTP unavailable')));
    try {
        $service->issue($admin, $payer, 'admin_ticket_issue', $context, '127.0.0.1', 'PHPUnit');
        $this->fail('Expected SMTP failure.');
    } catch (RuntimeException) {
        $this->assertSame(0, WebWalletVerificationChallenge::query()->where('status', 'pending')->count());
        $this->assertSame(1, WebWalletVerificationChallenge::query()->where('status', 'delivery_failed')->count());
    }
}
```

For changed-context coverage, call `consume()` with `array_merge($context, ['amount' => '151.00'])` and assert the challenge records a failed attempt and no consumed timestamp.

- [ ] **Step 2: Run tests and verify the challenge classes are missing**

Run: `vendor\bin\phpunit.bat tests\Feature\WebWalletVerificationTest.php`

Expected: FAIL because the model, migration, and service do not exist.

- [ ] **Step 3: Create challenge persistence**

Create `web_wallet_verification_challenges` with foreign keys to `users` and `mobile_users`; `email`, `purpose`, `context_fingerprint`, JSON `context`, `code_hash`, `status`, `attempts`, `send_count`, `created_ip`, `last_sent_ip`, `last_failed_ip`, `consumed_ip`, `user_agent`, `last_failed_at`, `last_sent_at`, `expires_at`, `consumed_at`, `superseded_at`, and timestamps. Index `(user_id, mobile_user_id, purpose, status)` and `(email, last_sent_at)`.

```php
Schema::create('web_wallet_verification_challenges', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('mobile_user_id')->constrained('mobile_users')->cascadeOnDelete();
    $table->string('email');
    $table->string('purpose', 80);
    $table->char('context_fingerprint', 64);
    $table->json('context');
    $table->string('code_hash');
    $table->string('status', 32)->default('sending');
    $table->unsignedTinyInteger('attempts')->default(0);
    $table->unsignedTinyInteger('send_count')->default(0);
    $table->string('created_ip', 45)->nullable();
    $table->string('last_sent_ip', 45)->nullable();
    $table->string('last_failed_ip', 45)->nullable();
    $table->string('consumed_ip', 45)->nullable();
    $table->string('user_agent', 512)->nullable();
    $table->timestamp('last_failed_at')->nullable();
    $table->timestamp('last_sent_at')->nullable();
    $table->timestamp('expires_at');
    $table->timestamp('consumed_at')->nullable();
    $table->timestamp('superseded_at')->nullable();
    $table->timestamps();
    $table->index(['user_id', 'mobile_user_id', 'purpose', 'status'], 'web_wallet_challenge_actor_status');
    $table->index(['email', 'last_sent_at'], 'web_wallet_challenge_email_sent');
});
```

```php
final class WebWalletVerificationChallenge extends Model
{
    protected $guarded = [];

    protected $hidden = ['code_hash'];

    protected $casts = [
        'context' => 'array',
        'attempts' => 'integer',
        'send_count' => 'integer',
        'last_failed_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];
}
```

- [ ] **Step 4: Implement issue, fingerprint, and consume behaviour**

Use `random_int(0, 999999)` plus `str_pad(..., 6, '0', STR_PAD_LEFT)`, `Hash::make()`, `hash('sha256', json_encode($normalizedContext, JSON_THROW_ON_ERROR))`, `DynamicSmtpMailer::sendRaw()`, and row locks. Persist invalid-attempt increments by returning an error result from the transaction and throwing `ValidationException` only after it commits.

```php
public function fingerprint(array $context): string
{
    $normalize = function (mixed $value) use (&$normalize): mixed {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map($normalize, $value);
        }
        ksort($value);
        return array_map($normalize, $value);
    };

    return hash('sha256', json_encode($normalize($context), JSON_THROW_ON_ERROR));
}

public function issue(User $admin, MobileUser $payer, string $purpose, array $context, ?string $ip, ?string $userAgent): WebWalletVerificationChallenge
{
    $email = strtolower(trim((string) $admin->email));
    if ($email !== strtolower(trim((string) $payer->email)) || ! $payer->canUseCommunity()) {
        throw ValidationException::withMessages(['payment_method' => 'Your linked wallet account could not be verified.']);
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $challenge = DB::transaction(function () use ($admin, $payer, $email, $purpose, $context, $code, $ip, $userAgent): WebWalletVerificationChallenge {
        User::query()->lockForUpdate()->findOrFail($admin->id);
        $this->assertSendAllowed($admin, $payer, $email, $purpose, $ip);

        return WebWalletVerificationChallenge::query()->create([
            'user_id' => $admin->id, 'mobile_user_id' => $payer->id, 'email' => $email,
            'purpose' => $purpose, 'context' => $context, 'context_fingerprint' => $this->fingerprint($context),
            'code_hash' => Hash::make($code), 'status' => 'sending', 'attempts' => 0, 'send_count' => 0,
            'created_ip' => $ip, 'user_agent' => substr((string) $userAgent, 0, 512), 'expires_at' => now()->addMinutes(10),
        ]);
    });

    try {
        $this->mailer->sendRaw($email, 'Confirm your Goshen wallet payment', "Your wallet verification code is {$code}. It expires in 10 minutes.");
    } catch (Throwable $exception) {
        $challenge->forceFill(['status' => 'delivery_failed'])->save();
        throw new RuntimeException('The wallet verification email could not be sent.', previous: $exception);
    }

    DB::transaction(function () use ($challenge, $admin, $payer, $purpose, $ip): void {
        WebWalletVerificationChallenge::query()
            ->where('user_id', $admin->id)->where('mobile_user_id', $payer->id)
            ->where('purpose', $purpose)->where('status', 'pending')->whereKeyNot($challenge->id)
            ->update(['status' => 'superseded', 'superseded_at' => now()]);
        $challenge->forceFill(['status' => 'pending', 'send_count' => 1, 'last_sent_at' => now(), 'last_sent_ip' => $ip])->save();
    });

    return $challenge->fresh();
}

private function assertSendAllowed(User $admin, MobileUser $payer, string $email, string $purpose, ?string $ip): void
{
    $query = WebWalletVerificationChallenge::query()
        ->where('user_id', $admin->id)
        ->where('mobile_user_id', $payer->id)
        ->where('email', $email)
        ->where('purpose', $purpose)
        ->where('created_ip', $ip);

    $latest = (clone $query)->latest('created_at')->first();
    if ($latest?->created_at?->gt(now()->subSeconds(60))) {
        throw ValidationException::withMessages(['wallet_otp' => 'Wait 60 seconds before requesting another code.']);
    }
    if ((clone $query)->where('created_at', '>=', now()->subHour())->count() >= 5) {
        throw ValidationException::withMessages(['wallet_otp' => 'The hourly wallet verification email limit has been reached.']);
    }
}
```

The locked web-user row serializes challenge reservations for the same admin. The rate query counts every reserved email attempt, including delivery failures, so repeated SMTP failures cannot bypass the hourly limit.

```php
$result = DB::transaction(function () use ($challenge, $admin, $payer, $purpose, $context, $code, $ip, $userAgent): array {
    $locked = WebWalletVerificationChallenge::query()->lockForUpdate()->findOrFail($challenge->id);
    $invalid = $locked->status !== 'pending'
        || $locked->expires_at?->isPast()
        || $locked->user_id !== $admin->id
        || $locked->mobile_user_id !== $payer->id
        || $locked->purpose !== $purpose
        || ! hash_equals($locked->context_fingerprint, $this->fingerprint($context));

    if ($invalid || ! Hash::check(trim($code), $locked->code_hash)) {
        $attempts = $locked->attempts + 1;
        $locked->forceFill([
            'attempts' => $attempts,
            'status' => $attempts >= 5 ? 'locked' : ($locked->expires_at?->isPast() ? 'expired' : $locked->status),
            'last_failed_at' => now(),
            'last_failed_ip' => $ip,
        ])->save();

        return ['challenge' => $locked, 'error' => 'The wallet verification code is invalid or expired.'];
    }

    $locked->forceFill([
        'status' => 'consumed',
        'consumed_at' => now(),
        'consumed_ip' => $ip,
        'user_agent' => substr((string) $userAgent, 0, 512),
    ])->save();

    return ['challenge' => $locked, 'error' => null];
});

if ($result['error'] !== null) {
    throw ValidationException::withMessages(['wallet_otp' => $result['error']]);
}

return $result['challenge']->fresh();
```

On issue/resend, reject sends inside sixty seconds, reject the sixth send in an hour for the same admin/payer/email/IP, supersede older pending challenges, and mark `delivery_failed` if SMTP throws. Never include the plaintext code in logs or exception messages.

- [ ] **Step 5: Run challenge tests**

Run: `vendor\bin\phpunit.bat tests\Feature\WebWalletVerificationTest.php`

Expected: PASS for issue, expiry, attempts, resend, hourly limit, context mismatch, replay, and SMTP failure.

- [ ] **Step 6: Commit and push**

```powershell
git add app/Models/WebWalletVerificationChallenge.php app/Services/WebWalletVerificationService.php database/migrations/2026_07_10_161000_create_web_wallet_verification_challenges_table.php tests/Feature/WebWalletVerificationTest.php
git commit -m "Add email verification for web wallet spending"
git push origin main
```

### Task 3A: Enforce One Full Payment and Repair Legacy Plan Records

**Files:**
- Create: `app/Services/GoshenSingleFullPaymentService.php`
- Create: `database/migrations/2026_07_10_162000_enforce_single_full_goshen_payments.php`
- Modify: `config/event-installments.php`
- Modify: `database/seeders/GoshenRetreatDemoSeeder.php`
- Modify: `app/Services/GoshenVoucherService.php`
- Modify: `app/Services/GoshenWalletService.php`
- Modify: `app/Http/Controllers/Api/GoshenRetreatController.php`
- Modify: `app/Filament/Resources/GoshenBookingResource/Pages/ViewGoshenBooking.php`
- Modify package settlement only as needed to make full payment the Goshen invariant.
- Create: `tests/Feature/GoshenSingleFullPaymentTest.php`

**Interfaces:**
- Produces: `GoshenSingleFullPaymentService::createForBooking(Booking $booking): PaymentInstallment`
- Produces: `GoshenSingleFullPaymentService::assertPayable(Booking $booking, PaymentInstallment $record): void`
- Produces: an idempotent repair that never deletes or invents completed financial history.

- [ ] **Step 1: Write failing invariant tests**

Cover creation of exactly one sequence-one record for the complete booking total with `payment_plan_id=null`; rejection of partial amounts, positive partial `paid_amount`, multiple rows, linked plans, and mismatched currency/amount; and rejection through voucher, wallet, card checkout, and offline settlement entry points. Prove wallet top-ups and savings goals still work and do not create a booking or payment row.

- [ ] **Step 2: Write migration tests for every legacy class**

Cover: unpaid unambiguous multi-row bookings consolidate to one full record; cancelled bookings remain cancelled; all plans become inactive and bookings are detached; pending checkout artifacts are expired/cancelled; completed transactions, applied voucher usage, paid rows, or issued tickets are preserved and marked `legacy_payment_review_required`; repeated migration execution is safe.

- [ ] **Step 3: Implement the full-payment service and guard active entry points**

Retain `PaymentInstallment` only as the existing canonical payable model. Never invoke `PaymentPlanService`. A payable booking must have no plan, one sequence-one row, the full total and currency, zero partial paid amount before settlement, and a transaction for the full amount. Replace all "first unpaid installment" selection in active Goshen payment flows with the guard.

- [ ] **Step 4: Permanently disable plan creation**

Set package API/admin route flags to literal `false`, remove payment-plan creation and installment language/permission grants from the Goshen demo seeder, and add route/config tests proving environment values cannot re-enable plan management.

- [ ] **Step 5: Implement conservative legacy repair**

Deactivate plans, detach bookings, and switch off scheduled auto-charge fields where present. Consolidate only when there is no paid transaction, applied voucher usage, paid row, issued ticket, or positive paid amount. Preserve and flag ambiguous/completed history for manual review. Do not delete payment transactions, voucher usages, tickets, or paid ledger entries.

- [ ] **Step 6: Verify and commit**

Run the focused invariant/migration tests plus existing Goshen booking, voucher, wallet, checkout, reporting, and package settlement regressions. Run `php artisan route:list --path=event-installments --no-ansi`, `php artisan migrate --pretend --no-interaction`, and `git diff --check`. Commit the verified hardening before proceeding to admin issuance.

### Task 3: Replace Complimentary Issuance with Paid Voucher and Wallet Settlement

**Files:**
- Create: `app/Services/GoshenAdminWalletPaymentService.php`
- Modify: `app/Services/GoshenAdminTicketIssuanceService.php`
- Rewrite: `tests/Feature/GoshenAdminTicketIssuanceTest.php`

**Interfaces:**
- Produces: `GoshenAdminWalletPaymentService::settle(Booking $booking, PaymentInstallment $fullPaymentRecord, GoshenWallet $wallet, MobileUser $payer, MobileUser $beneficiary, User $admin): PaymentTransaction`
- Consumes: the Task 3A one-full-payment guard; it must not create or accept a plan, deposit, partial amount, or future schedule.
- Produces: `GoshenAdminTicketIssuanceService::verificationContext(MobileUser $member, EventTicketType $ticketType, string $reason): array`
- Produces: `GoshenAdminTicketIssuanceService::issue(MobileUser $member, EventTicketType $ticketType, User $admin, string $reason, string $paymentMethod, ?string $voucherCode = null, ?WebWalletVerificationChallenge $challenge = null, ?string $walletCode = null, ?string $ip = null, ?string $userAgent = null): Ticket`

- [ ] **Step 1: Replace complimentary assertions with failing paid-flow tests**

Voucher test assertions:

```php
$ticket = $service->issue($member, $ticketType, $admin, 'Front desk registration', 'voucher', $voucherCode);
$booking = $ticket->booking()->with(['lines', 'installments.transactions'])->firstOrFail();

$this->assertSame('150.00', $booking->total);
$this->assertSame('150.00', $booking->paid_total);
$this->assertSame(BookingStatus::Paid, $booking->status);
$this->assertDatabaseHas('ei_payment_transactions', [
    'booking_id' => $booking->id,
    'gateway' => 'voucher',
    'amount' => 150,
    'status' => 'paid',
]);
$this->assertDatabaseHas('goshen_voucher_usages', ['booking_id' => $booking->id, 'amount' => 150]);
$this->assertArrayNotHasKey('complimentary', $booking->metadata ?? []);
```

Wallet test assertions:

```php
$ticket = $service->issue($member, $ticketType, $admin, 'Front desk registration', 'wallet', null, $challenge, $code, '127.0.0.1', 'PHPUnit');
$booking = $ticket->booking()->with('installments.transactions')->firstOrFail();

$this->assertSame('350.00', $payer->wallet()->firstOrFail()->balance);
$this->assertDatabaseHas('goshen_wallet_ledger_entries', ['wallet_id' => $payer->wallet->id, 'type' => 'retreat_payment', 'amount' => 150]);
$this->assertDatabaseHas('ei_payment_transactions', ['booking_id' => $booking->id, 'gateway' => 'wallet', 'status' => 'paid']);
$this->assertSame($member->id, $booking->customer_id);
$this->assertSame($admin->id, data_get($booking->metadata, 'issued_by_admin_id'));
```

Use explicit before/after assertions for wallet failure paths:

```php
public function test_invalid_wallet_code_leaves_no_financial_records(): void
{
    [$member, $ticketType, $admin, $payer, $challenge, $code] = $this->walletIssuanceFixture(balance: 500);
    $wrongCode = $code === '000000' ? '999999' : '000000';
    $beforeBalance = $payer->wallet()->firstOrFail()->balance;
    try {
        app(GoshenAdminTicketIssuanceService::class)->issue(
            $member, $ticketType, $admin, 'Front desk registration', 'wallet', null,
            $challenge, $wrongCode, '127.0.0.1', 'PHPUnit',
        );
        $this->fail('Expected wallet verification failure.');
    } catch (ValidationException) {
        $this->assertDatabaseCount('ei_bookings', 0);
        $this->assertDatabaseCount('ei_payment_transactions', 0);
        $this->assertDatabaseCount('goshen_wallet_ledger_entries', 0);
        $this->assertDatabaseCount('ei_tickets', 0);
        $this->assertSame($beforeBalance, $payer->wallet()->firstOrFail()->balance);
    }
}

public function test_insufficient_or_wrong_currency_wallet_rolls_back_financial_records(): void
{
    foreach ([['balance' => 149, 'currency' => 'GBP'], ['balance' => 500, 'currency' => 'USD']] as $walletState) {
        [$member, $ticketType, $admin, $payer, $challenge, $code] = $this->walletIssuanceFixture(...$walletState);
        try {
            app(GoshenAdminTicketIssuanceService::class)->issue(
                $member, $ticketType, $admin, 'Front desk registration', 'wallet', null,
                $challenge, $code, '127.0.0.1', 'PHPUnit',
            );
        } catch (ValidationException) {
            $this->assertDatabaseMissing('ei_bookings', ['customer_id' => $member->id]);
            $this->assertDatabaseMissing('goshen_wallet_ledger_entries', ['wallet_id' => $payer->wallet()->firstOrFail()->id, 'type' => 'retreat_payment']);
        }
    }
}
```

Retain and update the existing duplicate, blocked-member, unpublished-event, unrelated-admin, and issue-only detail-denial tests. Add invalid/exhausted voucher assertions using `assertDatabaseMissing('ei_payment_transactions', ['gateway' => 'voucher'])` and ensure only the same-email payer wallet ID can appear in a wallet payment payload.

Add a zero-price test and reject admin issuance when the selected ticket price is not greater than zero; this flow must never turn a free ticket type into a paid-looking transaction.

- [ ] **Step 2: Run paid issuance tests and verify old complimentary behaviour fails**

Run: `vendor\bin\phpunit.bat tests\Feature\GoshenAdminTicketIssuanceTest.php`

Expected: FAIL because the old service creates `total=0`, no full-payment record/transaction, and directly issues a ticket.

- [ ] **Step 3: Implement canonical admin wallet settlement**

```php
public function settle(
    Booking $booking,
    PaymentInstallment $installment,
    GoshenWallet $wallet,
    MobileUser $payer,
    MobileUser $beneficiary,
    User $admin,
): PaymentTransaction {
    $wallet = GoshenWallet::query()
        ->whereKey($wallet->id)
        ->where('mobile_user_id', $payer->id)
        ->lockForUpdate()
        ->firstOrFail();

    $amount = round((float) $installment->amount, 2);
    if (strtoupper((string) $wallet->currency) !== strtoupper((string) $booking->currency)) {
        throw ValidationException::withMessages(['payment_method' => 'Your wallet currency does not match this ticket.']);
    }
    if ((float) $wallet->balance + 0.01 < $amount) {
        throw ValidationException::withMessages(['payment_method' => 'Your wallet balance is not enough for this ticket.']);
    }

    $reference = 'gw_admin_ticket_' . Str::ulid();
    $wallet->decrement('balance', $amount);
    $wallet->ledgerEntries()->create([
        'type' => 'retreat_payment', 'status' => 'paid', 'currency' => $booking->currency,
        'amount' => $amount, 'gateway' => 'wallet', 'provider_reference' => $reference,
        'metadata' => ['booking_id' => $booking->id, 'payer_mobile_user_id' => $payer->id, 'beneficiary_mobile_user_id' => $beneficiary->id, 'payer_admin_user_id' => $admin->id],
        'settled_at' => now(),
    ]);
    $transaction = PaymentTransaction::query()->create([
        'booking_id' => $booking->id, 'installment_id' => $installment->id,
        'gateway' => 'wallet', 'provider_reference' => $reference,
        'currency' => $booking->currency, 'amount' => $amount, 'status' => 'pending',
        'payload' => ['source' => 'filament_admin_ticket_issue', 'wallet_id' => $wallet->id, 'payer_mobile_user_id' => $payer->id, 'beneficiary_mobile_user_id' => $beneficiary->id, 'payer_admin_user_id' => $admin->id],
    ]);
    $this->settlements->markPaid($transaction, $amount, (string) $booking->currency);

    return $transaction->fresh();
}
```

Call `WalletSecurityResetService::assertWalletActionsAllowed($payer)` before the debit.

- [ ] **Step 4: Rewrite issuance around normal pending financial records**

Before the financial transaction, resolve the admin's linked payer and consume the OTP for wallet payment. Inside one financial transaction create booking `subtotal=total=listPrice`, `paid_total=0`, `status=Pending`, `payment_plan_id=null`; booking line; attendee; and use `GoshenSingleFullPaymentService` to create one pending canonical record for the complete amount. For voucher call:

```php
$usage = $this->vouchers->redeemForBooking(
    $booking,
    $installment,
    (string) $voucherCode,
    $member,
    $payer,
    'filament_admin_ticket_issue',
    $admin,
);
```

For wallet call `GoshenAdminWalletPaymentService::settle()`. After either settlement, load the ticket created by `PaymentSettlementService`, add only safe payment/audit references, and create `EventAuditLog(action: 'admin_ticket_issued')`. Remove `TicketIssuer` injection and all `complimentary`, `waived_amount`, `total=0`, direct `BookingStatus::Paid`, and direct ticket-issuance code.

- [ ] **Step 5: Run paid issuance and related payment tests**

Run: `vendor\bin\phpunit.bat tests\Feature\GoshenAdminTicketIssuanceTest.php tests\Feature\GoshenWalletVoucherWithdrawalTest.php tests\Feature\WalletSecurityResetFlowTest.php`

Expected: PASS with voucher usage, wallet debit, paid transaction, settled booking, ticket, and audit records.

- [ ] **Step 6: Commit and push**

```powershell
git add app/Services/GoshenAdminWalletPaymentService.php app/Services/GoshenAdminTicketIssuanceService.php tests/Feature/GoshenAdminTicketIssuanceTest.php
git commit -m "Settle admin-issued tickets through real payments"
git push origin main
```

### Task 4: Filament Voucher/Wallet Form and OTP Interaction

**Files:**
- Modify: `app/Filament/Resources/GoshenTicketResource.php`
- Modify: `app/Filament/Resources/GoshenTicketResource/Pages/CreateGoshenTicket.php`
- Modify: `tests/Feature/GoshenAdminTicketIssuanceTest.php`

**Interfaces:**
- Consumes: `LinkedMobileAccountService`, `WebWalletVerificationService`, and expanded `GoshenAdminTicketIssuanceService` interfaces from Tasks 1–3.
- Produces: paid create form with conditional voucher or wallet controls and an OTP send action.

- [ ] **Step 1: Write failing Livewire form tests**

```php
Livewire::actingAs($admin)
    ->test(CreateGoshenTicket::class)
    ->assertSchemaComponentExists('payment_method')
    ->assertSchemaComponentExists('voucher_code')
    ->assertSchemaComponentExists('wallet_otp')
    ->fillForm([
        'customer_id' => $member->id,
        'event_id' => $ticketType->event_id,
        'ticket_type_id' => $ticketType->id,
        'issuance_reason' => 'Front desk registration',
        'payment_method' => 'wallet',
    ])
    ->callAction('sendWalletVerificationCode')
    ->assertHasNoActionErrors();
```

Add paid form submissions for each method:

```php
Livewire::actingAs($admin)
    ->test(CreateGoshenTicket::class)
    ->fillForm([
        'customer_id' => $member->id,
        'event_id' => $ticketType->event_id,
        'ticket_type_id' => $ticketType->id,
        'issuance_reason' => 'Front desk registration',
        'payment_method' => 'voucher',
        'voucher_code' => $voucherCode,
    ])
    ->call('create')
    ->assertHasNoFormErrors();

$walletPage = Livewire::actingAs($admin)
    ->test(CreateGoshenTicket::class)
    ->fillForm([
        'customer_id' => $member->id,
        'event_id' => $ticketType->event_id,
        'ticket_type_id' => $ticketType->id,
        'issuance_reason' => 'Front desk registration',
        'payment_method' => 'wallet',
    ])
    ->callAction('sendWalletVerificationCode')
    ->fillForm(['wallet_otp' => $capturedCode])
    ->call('create')
    ->assertHasNoFormErrors();

$this->assertDatabaseCount('ei_tickets', 2);
```

Retain issue-only authorization tests proving no index/detail access.

- [ ] **Step 2: Run Livewire tests and verify payment controls are missing**

Run: `vendor\bin\phpunit.bat tests\Feature\GoshenAdminTicketIssuanceTest.php --filter=page`

Expected: FAIL because the form still describes complimentary issuance and has no payment/OTP controls.

- [ ] **Step 3: Replace complimentary form fields**

Add a live `payment_method` select with only `voucher` and `wallet`; conditional required `voucher_code`; hidden `wallet_challenge_id`; conditional six-digit numeric `wallet_otp`; amount/currency and payer-wallet summary placeholders; and a `sendWalletVerificationCode` action. Clear challenge and OTP state whenever recipient, event, ticket type, reason, or payment method changes.

```php
Forms\Components\Select::make('payment_method')
    ->options(['voucher' => 'Voucher', 'wallet' => 'My Goshen wallet'])
    ->live()->required(),
Forms\Components\TextInput::make('voucher_code')
    ->visible(fn (Get $get): bool => $get('payment_method') === 'voucher')
    ->required(fn (Get $get): bool => $get('payment_method') === 'voucher'),
Forms\Components\Hidden::make('wallet_challenge_id'),
Forms\Components\TextInput::make('wallet_otp')
    ->label('Six-digit email verification code')
    ->numeric()->length(6)
    ->visible(fn (Get $get): bool => $get('payment_method') === 'wallet')
    ->required(fn (Get $get): bool => $get('payment_method') === 'wallet'),
```

- [ ] **Step 4: Implement page OTP send and paid create handlers**

`sendWalletVerificationCode` validates the selected recipient/ticket/reason, resolves the authenticated admin's linked payer, builds `verificationContext()`, calls `WebWalletVerificationService::issue()`, stores the challenge ID in form state, masks the destination in the success notification, and exposes no code. `handleRecordCreation()` passes voucher or wallet fields, request IP, and browser user-agent to `GoshenAdminTicketIssuanceService::issue()`.

Expose the send operation as a secondary create-page action so it is callable and testable by name:

```php
protected function getFormActions(): array
{
    return [
        Action::make('sendWalletVerificationCode')
            ->label('Email wallet verification code')
            ->visible(fn (): bool => ($this->data['payment_method'] ?? null) === 'wallet')
            ->action(fn (): mixed => $this->sendWalletVerificationCode()),
        ...parent::getFormActions(),
    ];
}
```

Implement the action and create handler with the same ticket-type resolver already used by the page:

```php
public function sendWalletVerificationCode(): void
{
    $data = $this->form->getRawState();
    $member = MobileUser::query()->findOrFail($data['customer_id']);
    $ticketType = $this->selectedTicketType($data);
    $admin = auth()->user();
    abort_unless($admin instanceof User, 403);

    $payer = app(LinkedMobileAccountService::class)->forAdmin($admin);
    if (! $payer) {
        throw ValidationException::withMessages(['payment_method' => 'Your linked wallet account could not be verified.']);
    }

    $issuer = app(GoshenAdminTicketIssuanceService::class);
    $challenge = app(WebWalletVerificationService::class)->issue(
        $admin,
        $payer,
        'admin_ticket_issue',
        $issuer->verificationContext($member, $ticketType, (string) $data['issuance_reason']),
        request()->ip(),
        request()->userAgent(),
    );

    $this->data['wallet_challenge_id'] = $challenge->id;
    $this->data['wallet_otp'] = null;
    Notification::make()->success()->title('Verification code sent')
        ->body('Enter the six-digit code sent to ' . $this->maskedEmail($payer->email) . '.')->send();
}

protected function handleRecordCreation(array $data): Model
{
    $ticketType = $this->selectedTicketType($data);
    $admin = auth()->user();
    abort_unless($admin instanceof User, 403);

    return app(GoshenAdminTicketIssuanceService::class)->issue(
        MobileUser::query()->findOrFail($data['customer_id']),
        $ticketType,
        $admin,
        (string) $data['issuance_reason'],
        (string) $data['payment_method'],
        filled($data['voucher_code'] ?? null) ? (string) $data['voucher_code'] : null,
        filled($data['wallet_challenge_id'] ?? null) ? WebWalletVerificationChallenge::query()->findOrFail($data['wallet_challenge_id']) : null,
        filled($data['wallet_otp'] ?? null) ? (string) $data['wallet_otp'] : null,
        request()->ip(),
        request()->userAgent(),
    );
}

private function selectedTicketType(array $data): EventTicketType
{
    return EventTicketType::query()
        ->whereKey($data['ticket_type_id'] ?? null)
        ->where('event_id', $data['event_id'] ?? null)
        ->firstOrFail();
}

private function maskedEmail(string $email): string
{
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    return substr($local, 0, 1) . str_repeat('*', max(2, strlen($local) - 1)) . '@' . $domain;
}
```

- [ ] **Step 5: Run form, authorization, and syntax checks**

Run: `vendor\bin\phpunit.bat tests\Feature\GoshenAdminTicketIssuanceTest.php tests\Feature\AdminAccessTest.php`

Run: `php -l app\Filament\Resources\GoshenTicketResource.php; php -l app\Filament\Resources\GoshenTicketResource\Pages\CreateGoshenTicket.php`

Expected: all tests PASS and both syntax checks report no errors.

- [ ] **Step 6: Commit and push**

```powershell
git add app/Filament/Resources/GoshenTicketResource.php app/Filament/Resources/GoshenTicketResource/Pages/CreateGoshenTicket.php tests/Feature/GoshenAdminTicketIssuanceTest.php
git commit -m "Add paid ticket controls to admin"
git push origin main
```

### Task 5: Full Verification, Reporting Proof, and Deployment

**Files:**
- Modify: `tests/Feature/GoshenAdminTicketIssuanceTest.php`
- No Flutter source changes.

**Interfaces:**
- Consumes all prior task outputs.
- Produces verified staging and production deployments with migrated wallet/challenge schema.

- [ ] **Step 1: Add an explicit sales/gateway reporting assertion**

Create one voucher-paid and one wallet-paid admin issuance, then assert the paid transaction aggregate used by `managementSummary()` returns `300.00` total with `150.00` per gateway and that neither booking is classified as free.

```php
$transactions = PaymentTransaction::query()->whereIn('booking_id', [$voucherBooking->id, $walletBooking->id])->where('status', 'paid')->get();
$this->assertSame(300.0, round((float) $transactions->sum('amount'), 2));
$this->assertSame(150.0, round((float) $transactions->where('gateway', 'voucher')->sum('amount'), 2));
$this->assertSame(150.0, round((float) $transactions->where('gateway', 'wallet')->sum('amount'), 2));
$this->assertTrue(collect([$voucherBooking, $walletBooking])->every(fn (Booking $booking): bool => (float) $booking->total > 0 && (float) $booking->paid_total === (float) $booking->total));
```

- [ ] **Step 2: Run all focused regression suites fresh**

Run: `vendor\bin\phpunit.bat tests\Feature\AccountWalletProvisioningTest.php tests\Feature\WebWalletVerificationTest.php tests\Feature\GoshenAdminTicketIssuanceTest.php tests\Feature\MergedAccountCredentialTest.php tests\Feature\TriumphantIdServiceTest.php tests\Feature\GoshenWalletVoucherWithdrawalTest.php tests\Feature\WalletSecurityResetFlowTest.php tests\Feature\AdminAccessTest.php`

Expected: PASS with zero failures.

- [ ] **Step 3: Run migration, route, whitespace, and repository checks**

```powershell
php artisan migrate --pretend --no-interaction
php artisan route:list --path=admin/goshen-tickets --no-ansi
git diff --check
git status --short --branch
```

Expected: both new migrations appear valid; create/index/view ticket routes exist; no whitespace errors; only known unrelated untracked files remain.

- [ ] **Step 4: Commit any reporting-only test addition and push**

```powershell
git add tests/Feature/GoshenAdminTicketIssuanceTest.php
git commit -m "Verify admin ticket payment reporting"
git push origin main
```

- [ ] **Step 5: Deploy and verify staging**

Deploy only committed files to `/home/cels/projects/staging-goshen.shotfaz.com`, create a timestamped backup, set `.codex_deploy_revision`, then run:

```bash
php artisan migrate --force --no-interaction
php artisan optimize:clear --no-interaction
php artisan route:list --path=admin/goshen-tickets --no-ansi
php artisan migrate:status --no-ansi
```

Verify the authenticated staging UI for voucher issuance, wallet OTP delivery, wrong-code rejection, successful wallet debit, ticket availability, and gateway reporting only with an approved controlled staging account. Confirm `/admin/goshen-tickets/create` redirects unauthenticated requests to `/admin/login`.

- [ ] **Step 6: Deploy production only after staging passes**

Deploy the same committed revision to `/home/cels/projects/goshen.shotfaz.com`, create a timestamped backup, run the same migration/cache/route checks, and verify unauthenticated route guarding. Do not create a production booking, debit a production wallet, or consume a production voucher during smoke verification.

- [ ] **Step 7: Final repository and Flutter non-change verification**

```powershell
git status --short --branch
git -C C:\Appbuild\Goshen-Flutter-App status --short --branch
```

Expected: Laravel `main` matches `origin/main` with only pre-existing unrelated untracked files; Flutter has no changes from this implementation.
