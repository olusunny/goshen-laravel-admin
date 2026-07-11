# Goshen Voucher Purpose and Wallet Demo Reset Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship explicit payment-versus-wallet-funding Goshen vouchers across Laravel admin and Flutter Control Hub, then remove confirmed demo voucher and wallet financial data from staging and production.

**Architecture:** Persist voucher purpose as a non-null `goshen_vouchers.purpose` value and enforce it inside the locked voucher-redemption service. Laravel Filament and Flutter select the same two values, while an Artisan-only reset command clears demo financial state without touching identities, bookings, tickets, ordinary payment records, wallet security state, or Stripe customer references.

**Tech Stack:** Laravel 12, PHP 8.2, Filament 5, Eloquent, MySQL, PHPUnit 11, Flutter/Dart, Dio, Flutter test.

## Global Constraints

- Use only `C:\Appbuild\Goshen-Laravel-Admin-Staging` and `C:\Appbuild\Goshen-Flutter-App`.
- The only voucher purposes are `payments` (**For Payments**) and `wallet_funding` (**Wallet Funding**).
- Existing vouchers migrate to `payments`; a voucher purpose cannot change after creation.
- Wallet-funding vouchers may only credit the authenticated member's own wallet; payment vouchers may not credit a wallet.
- Do not introduce complimentary, zero-payment, partial, scheduled, or instalment Goshen payment paths.
- Used vouchers must remain auditable and cannot be deleted or voided after the one-off confirmed demo reset.
- The reset deletes only demo Goshen voucher and wallet financial state; it must preserve users, wallet ownership/security fields, Triumphant IDs, bookings, tickets, ordinary payments, and Stripe customer references.
- Run the reset on staging first, verify, then run it on production. Never log voucher codes, OTPs, credentials, or payment secrets.

---

## File structure

### Laravel repository

- `database/migrations/2026_07_11_030000_add_purpose_to_goshen_vouchers.php` — adds and backfills the indexed, non-null voucher-purpose field.
- `app/Models/GoshenVoucher.php` — owns purpose constants, labels, and unused/deletable predicates.
- `app/Services/GoshenVoucherService.php` — validates purpose inside the existing redemption transaction and returns it in API payloads.
- `app/Http/Controllers/Api/GoshenRetreatController.php` — validates mobile Control Hub generation requests and removes event scope for wallet vouchers.
- `app/Filament/Resources/GoshenVoucherResource.php` — purpose field, responsive generation form, purpose table/filter, and safe row/bulk actions.
- `app/Console/Commands/ResetGoshenDemoWalletVoucherData.php` — explicit confirmation-gated demo cleanup command.
- `tests/Feature/GoshenVoucherPurposeTest.php` — purpose migration/redemption/API regression coverage.
- `tests/Feature/GoshenVoucherAdminResourceTest.php` — admin table permission, deletion, and bulk-operation coverage.
- `tests/Feature/GoshenDemoWalletVoucherResetCommandTest.php` — reset scope and safety coverage.

### Flutter repository

- `lib/models/GoshenRetreat.dart` — exposes the purpose field and display labels in `GoshenVoucherInfo`.
- `lib/service/GoshenRetreatApi.dart` — sends the purpose and omits an event for wallet-funding voucher generation.
- `lib/screens/GoshenManagementHubScreen.dart` — adds the Control Hub purpose selector and conditional event generation input.
- `test/goshen_voucher_contract_test.dart` — verifies the generated-voucher JSON contract and purpose labels.

## Task 1: Integrate the reviewed paid-admin-ticket foundation

**Files:**
- Modify: `C:\Appbuild\Goshen-Laravel-Admin-Staging` Git history by merging `feature/paid-admin-ticket-issuance` into `main`.
- Test: `tests/Feature/GoshenAdminTicketIssuanceTest.php`
- Test: `tests/Feature/WebWalletVerificationTest.php`

**Interfaces:**
- Consumes: the reviewed `feature/paid-admin-ticket-issuance` branch at `f74d592`.
- Produces: `GoshenAdminTicketIssuanceService` on `main`, so the purpose guard added in Task 2 covers every booking-payment entry point.

- [ ] **Step 1: Verify the current branches and both worktrees are clean**

Run:

```powershell
git -C C:\Appbuild\Goshen-Laravel-Admin-Staging status --short
git -C C:\Appbuild\Goshen-Laravel-Admin-Staging log --oneline --decorate -5
git -C C:\Appbuild\Goshen-Laravel-Admin-Staging\.worktrees\paid-admin-ticket-issuance status --short
```

Expected: only the documented unrelated untracked planning files appear in the main worktree; the feature worktree has no tracked changes.

- [ ] **Step 2: Merge the reviewed foundation without discarding newer portal work**

Run:

```powershell
git -C C:\Appbuild\Goshen-Laravel-Admin-Staging merge --no-ff feature/paid-admin-ticket-issuance -m "Integrate paid admin ticket issuance"
git -C C:\Appbuild\Goshen-Laravel-Admin-Staging diff --check
```

Expected: one merge commit; resolve only a real textual conflict by retaining both the newer member-portal work and the reviewed paid-ticket code.

- [ ] **Step 3: Run the integrated payment and wallet-security suites**

Run:

```powershell
php artisan test tests/Feature/GoshenAdminTicketIssuanceTest.php tests/Feature/WebWalletVerificationTest.php
```

Expected: PASS; the existing voucher payment path remains a full paid transaction and the wallet OTP flow remains intact.

- [ ] **Step 4: Commit and push the merge**

Run:

```powershell
git -C C:\Appbuild\Goshen-Laravel-Admin-Staging push origin main
```

Expected: `origin/main` contains the merge commit.

## Task 2: Persist and enforce the voucher purpose

**Files:**
- Create: `database/migrations/2026_07_11_030000_add_purpose_to_goshen_vouchers.php`
- Modify: `app/Models/GoshenVoucher.php`
- Modify: `app/Services/GoshenVoucherService.php`
- Create: `tests/Feature/GoshenVoucherPurposeTest.php`

**Interfaces:**
- Consumes: `GoshenVoucherService::createVoucher(array $data, ?MobileUser $mobileActor = null, ?User $adminActor = null): array`.
- Produces: `GoshenVoucher::PURPOSE_PAYMENTS`, `GoshenVoucher::PURPOSE_WALLET_FUNDING`, `GoshenVoucher::purposeOptions(): array`, `GoshenVoucher::isUnused(): bool`, and `GoshenVoucherService` purpose checks used by web, mobile, and admin-ticket callers.

- [ ] **Step 1: Write failing redemption and legacy-default tests**

Create `tests/Feature/GoshenVoucherPurposeTest.php` with the following essential cases, reusing the event/member/booking factory helpers from `GoshenVoucherApiTest`:

```php
public function test_wallet_funding_voucher_cannot_pay_for_a_booking(): void
{
    [$booking, $installment, $member] = $this->bookingWithOutstandingPayment();
    $voucher = app(GoshenVoucherService::class)->createVoucher([
        'amount' => 100,
        'currency' => $booking->currency,
        'purpose' => GoshenVoucher::PURPOSE_WALLET_FUNDING,
    ]);

    $this->expectExceptionMessage('This voucher is only valid for wallet funding.');
    app(GoshenVoucherService::class)->redeemForBooking(
        $booking, $installment, $voucher['code'], $member, $member,
    );
}

public function test_payment_voucher_cannot_credit_a_wallet(): void
{
    $member = $this->member();
    $wallet = $this->walletFor($member);
    $voucher = app(GoshenVoucherService::class)->createVoucher([
        'amount' => 25,
        'currency' => 'GBP',
        'purpose' => GoshenVoucher::PURPOSE_PAYMENTS,
    ]);

    $this->expectExceptionMessage('This voucher is only valid for payments.');
    app(GoshenVoucherService::class)->redeemForWalletTopUp($wallet, $voucher['code'], $member, $member);
}
```

Also assert that a manually inserted legacy row has `purpose === 'payments'` after migration and that every mismatch leaves `used_count`, booking paid total, wallet balance, wallet ledger, and voucher usages unchanged.

- [ ] **Step 2: Run the focused test to verify the current contract fails**

Run:

```powershell
php artisan test tests/Feature/GoshenVoucherPurposeTest.php
```

Expected: FAIL because the current service permits the wrong redemption purpose and the database has no `purpose` column.

- [ ] **Step 3: Add the migration and immutable model vocabulary**

Create the migration with an indexed non-null field and a backfill-safe default:

```php
Schema::table('goshen_vouchers', function (Blueprint $table): void {
    $table->string('purpose', 32)
        ->default(GoshenVoucher::PURPOSE_PAYMENTS)
        ->index()
        ->after('event_id');
});

DB::table('goshen_vouchers')
    ->whereNull('purpose')
    ->update(['purpose' => GoshenVoucher::PURPOSE_PAYMENTS]);
```

In `GoshenVoucher`, add:

```php
public const PURPOSE_PAYMENTS = 'payments';
public const PURPOSE_WALLET_FUNDING = 'wallet_funding';

public static function purposeOptions(): array
{
    return [
        self::PURPOSE_PAYMENTS => 'For Payments',
        self::PURPOSE_WALLET_FUNDING => 'Wallet Funding',
    ];
}

public function isUnused(): bool
{
    return (int) $this->used_count === 0 && ! $this->usages()->exists();
}
```

Add `purpose` to the model casts as a string. Do not add `purpose` to the edit form: it is generation-time-only.

- [ ] **Step 4: Enforce the purpose while the existing voucher and wallet rows are locked**

In `GoshenVoucherService`, normalise and validate the generation input before creating a voucher, then persist the result:

```php
$purpose = (string) ($data['purpose'] ?? GoshenVoucher::PURPOSE_PAYMENTS);
if (! array_key_exists($purpose, GoshenVoucher::purposeOptions())) {
    throw new RuntimeException('Voucher purpose is not valid.');
}

// In the create payload:
'purpose' => $purpose,
```

Add one private guard and call it immediately after each locked voucher lookup:

```php
private function assertVoucherPurpose(GoshenVoucher $voucher, string $requiredPurpose): void
{
    if ($voucher->purpose !== $requiredPurpose) {
        $message = $requiredPurpose === GoshenVoucher::PURPOSE_WALLET_FUNDING
            ? 'This voucher is only valid for wallet funding.'
            : 'This voucher is only valid for payments.';

        throw new RuntimeException($message);
    }
}
```

Call the guard with `PURPOSE_PAYMENTS` from `redeemForBooking()` and with `PURPOSE_WALLET_FUNDING` from `redeemForWalletTopUp()`. Add `'purpose' => $voucher->purpose` to `voucherPayload()`.

- [ ] **Step 5: Run migration and service verification**

Run:

```powershell
php artisan migrate --pretend
php artisan test tests/Feature/GoshenVoucherPurposeTest.php tests/Feature/GoshenVoucherApiTest.php tests/Feature/GoshenWalletVoucherWithdrawalTest.php
vendor\bin\pint --dirty
```

Expected: PASS. A wallet voucher creates only `voucher_top_up` credit state; a payment voucher can settle only an eligible full payment.

- [ ] **Step 6: Commit the persistence and service boundary**

Run:

```powershell
git add database/migrations/2026_07_11_030000_add_purpose_to_goshen_vouchers.php app/Models/GoshenVoucher.php app/Services/GoshenVoucherService.php tests/Feature/GoshenVoucherPurposeTest.php
git commit -m "Enforce Goshen voucher purposes"
```

## Task 3: Expose purpose safely in the web admin and mobile API

**Files:**
- Modify: `app/Http/Controllers/Api/GoshenRetreatController.php`
- Modify: `app/Filament/Resources/GoshenVoucherResource.php`
- Create: `tests/Feature/GoshenVoucherAdminResourceTest.php`
- Modify: `tests/Feature/GoshenVoucherApiTest.php`

**Interfaces:**
- Consumes: `GoshenVoucher::purposeOptions()` and the Task 2 service contract.
- Produces: a required `purpose` field in web and Control Hub generation requests; purpose-aware, permission-protected table controls.

- [ ] **Step 1: Write failing API and resource tests**

Add these API assertions to `GoshenVoucherApiTest`:

```php
$this->postJson('/api/goshen-retreat/vouchers/generate', [
    'data' => [
        'api_token' => $manager->issueApiToken(),
        'purpose' => GoshenVoucher::PURPOSE_WALLET_FUNDING,
        'amount' => 25,
        'currency' => 'GBP',
        'quantity' => 1,
    ],
])->assertCreated()
  ->assertJsonPath('data.0.voucher.purpose', GoshenVoucher::PURPOSE_WALLET_FUNDING);
```

Assert invalid purpose returns 422, a wallet-funding request stores `event_id` as null, and an authorised manager can create both purposes. In `GoshenVoucherAdminResourceTest`, create one unused and one used voucher, then assert the unused record is deletable and the used record is neither deletable nor included in a bulk-delete result.

- [ ] **Step 2: Run the focused tests to confirm they fail**

Run:

```powershell
php artisan test tests/Feature/GoshenVoucherApiTest.php tests/Feature/GoshenVoucherAdminResourceTest.php
```

Expected: FAIL because the controller ignores `purpose` and the resource has no safe delete/bulk controls.

- [ ] **Step 3: Validate the Control Hub request and make wallet funding eventless**

In `generateVouchers()`, extend validation and resolve an event only for payment vouchers:

```php
'purpose' => ['required', Rule::in(array_keys(GoshenVoucher::purposeOptions()))],

if (($validated['purpose'] ?? null) === GoshenVoucher::PURPOSE_WALLET_FUNDING) {
    $validated['event_id'] = null;
} elseif (filled($validated['event_id'] ?? null)) {
    $event = $this->eventFromKey((string) $validated['event_id']);
    // retain the existing Goshen-event 404 response
    $validated['event_id'] = $event->id;
}
```

Import `Illuminate\Validation\Rule`. Keep the current `voucherManagerAccessError()` permission gate unchanged.

- [ ] **Step 4: Add responsive web generation and table controls**

In `GoshenVoucherResource::generateAction()`, insert a required, live Purpose select before the event select:

```php
Forms\Components\Select::make('purpose')
    ->options(GoshenVoucher::purposeOptions())
    ->default(GoshenVoucher::PURPOSE_PAYMENTS)
    ->required()
    ->live(),
```

Hide and dehydrate `event_id` when `purpose` is `wallet_funding`; add clear helper text that wallet-funding codes can only top up a member wallet. Add a badge `purpose` column and `SelectFilter` using `purposeOptions()`.

Add table record selection through `toolbarActions()`, with these actions:

```php
Actions\DeleteAction::make()
    ->visible(fn (GoshenVoucher $record): bool => $record->isUnused())
    ->requiresConfirmation();

Actions\BulkAction::make('voidSelected')
    ->label('Void selected unused vouchers')
    ->requiresConfirmation()
    ->action(function (Collection $records): void {
        $records->filter(fn (GoshenVoucher $record) => $record->isUnused())
            ->each(fn (GoshenVoucher $record) => $record->update(['status' => GoshenVoucher::STATUS_VOID]));
    });
```

The bulk Delete action must delete only `isUnused()` records and notify with separate deleted/skipped counts. Used records are skipped, never force-deleted. In `form()`, disable status dehydration for used records so an exhausted/used voucher cannot be changed to `void`:

```php
Forms\Components\Select::make('status')
    ->options([
        GoshenVoucher::STATUS_ACTIVE => 'Active',
        GoshenVoucher::STATUS_PAUSED => 'Paused',
        GoshenVoucher::STATUS_EXHAUSTED => 'Exhausted',
        GoshenVoucher::STATUS_VOID => 'Void',
    ])
    ->disabled(fn (?GoshenVoucher $record): bool => $record !== null && ! $record->isUnused())
    ->dehydrated(fn (?GoshenVoucher $record): bool => $record === null || $record->isUnused());
```

Never include `purpose` in `form()`.

- [ ] **Step 5: Run the admin/API suites and static checks**

Run:

```powershell
php artisan test tests/Feature/GoshenVoucherApiTest.php tests/Feature/GoshenVoucherAdminResourceTest.php tests/Feature/GoshenWalletVoucherWithdrawalTest.php
php -l app/Filament/Resources/GoshenVoucherResource.php
php -l app/Http/Controllers/Api/GoshenRetreatController.php
vendor\bin\pint --dirty
git diff --check
```

Expected: PASS; unauthorised users remain blocked, generated payloads expose the purpose, and used vouchers survive deletion attempts.

- [ ] **Step 6: Commit the admin and API contract**

Run:

```powershell
git add app/Http/Controllers/Api/GoshenRetreatController.php app/Filament/Resources/GoshenVoucherResource.php tests/Feature/GoshenVoucherApiTest.php tests/Feature/GoshenVoucherAdminResourceTest.php
git commit -m "Add purpose-aware Goshen voucher controls"
```

## Task 4: Add purpose selection to Flutter Control Hub

**Files:**
- Modify: `C:\Appbuild\Goshen-Flutter-App\lib/models/GoshenRetreat.dart`
- Modify: `C:\Appbuild\Goshen-Flutter-App\lib/service/GoshenRetreatApi.dart`
- Modify: `C:\Appbuild\Goshen-Flutter-App\lib/screens/GoshenManagementHubScreen.dart`
- Create: `C:\Appbuild\Goshen-Flutter-App\test/goshen_voucher_contract_test.dart`

**Interfaces:**
- Consumes: Laravel `purpose` values and the existing `canManageGoshenVouchers` capability.
- Produces: `GoshenVoucherInfo.purpose`, `GoshenVoucherInfo.purposeLabel`, and `GoshenRetreatApi.generateVouchers(..., required String purpose, GoshenRetreatEvent? event)`.

- [ ] **Step 1: Write the failing Flutter contract test**

Create `test/goshen_voucher_contract_test.dart`:

```dart
test('wallet funding voucher exposes the correct purpose label', () {
  final voucher = GoshenVoucherInfo.fromJson(const {
    'id': 1,
    'purpose': 'wallet_funding',
    'currency': 'GBP',
    'amount': 25,
  });

  expect(voucher.purpose, GoshenVoucherInfo.purposeWalletFunding);
  expect(voucher.purposeLabel, 'Wallet Funding');
});
```

Add a request-payload assertion that a wallet-funding generation request contains `purpose: 'wallet_funding'` and no `event_id`.

- [ ] **Step 2: Run the Flutter test to verify it fails**

Run:

```powershell
flutter test test/goshen_voucher_contract_test.dart
```

Expected: FAIL because the model and API have no purpose contract.

- [ ] **Step 3: Extend the model and API request builder**

In `GoshenVoucherInfo`, add constants and parsing:

```dart
static const purposePayments = 'payments';
static const purposeWalletFunding = 'wallet_funding';

final String purpose;

String get purposeLabel => purpose == purposeWalletFunding
    ? 'Wallet Funding'
    : 'For Payments';
```

Default absent legacy payloads to `purposePayments`. Change `generateVouchers` to accept `required String purpose` and `GoshenRetreatEvent? event`, then construct its data with this testable static method:

```dart
static Map<String, dynamic> voucherGenerationPayload({
  required Userdata user,
  required String label,
  required double amount,
  required String currency,
  required int quantity,
  required int maxUses,
  required String purpose,
  GoshenRetreatEvent? event,
}) => {
  'data': {
    'email': user.email,
    'api_token': user.apiToken,
    'label': label.trim(),
    'amount': amount,
    'currency': currency.trim().toUpperCase(),
    'quantity': quantity,
    'max_uses': maxUses,
    'purpose': purpose,
    if (event != null) 'event_id': event.publicId,
  },
};
```

Pass `voucherGenerationPayload(...)` directly to Dio. The test from Step 1 must call this static method and assert `payload['data']['purpose'] == 'wallet_funding'` plus `!payload['data'].containsKey('event_id')`.

- [ ] **Step 4: Add the Control Hub purpose selector and conditional event use**

In `_GoshenVoucherManagementScreenState`, initialise:

```dart
String _purpose = GoshenVoucherInfo.purposePayments;
```

Pass `_purpose` and an `onPurposeChanged` callback into `_VoucherGeneratePanel`. Render `_ManagedDropdown` with exactly:

```dart
items: const {
  GoshenVoucherInfo.purposePayments: 'For Payments',
  GoshenVoucherInfo.purposeWalletFunding: 'Wallet Funding',
},
```

When calling the API, use `event: _purpose == GoshenVoucherInfo.purposePayments ? _selectedEvent : null`. Show generated codes with both `amountLabel` and `purposeLabel`. Preserve the existing fresh wallet-security unlock before code generation and the existing `canManageGoshenVouchers` navigation gate.

- [ ] **Step 5: Verify the app contract and formatting**

Run:

```powershell
dart format lib/models/GoshenRetreat.dart lib/service/GoshenRetreatApi.dart lib/screens/GoshenManagementHubScreen.dart test/goshen_voucher_contract_test.dart
flutter test test/goshen_voucher_contract_test.dart
flutter analyze
```

Expected: PASS with no analyser errors; the user wallet screen remains a server-enforced top-up-only redemption surface.

- [ ] **Step 6: Commit and push the Flutter change**

Run:

```powershell
git -C C:\Appbuild\Goshen-Flutter-App add lib/models/GoshenRetreat.dart lib/service/GoshenRetreatApi.dart lib/screens/GoshenManagementHubScreen.dart test/goshen_voucher_contract_test.dart
git -C C:\Appbuild\Goshen-Flutter-App commit -m "Add voucher purpose selection to Control Hub"
git -C C:\Appbuild\Goshen-Flutter-App push origin main
```

## Task 5: Implement the one-off, confirmation-gated demo reset command

**Files:**
- Create: `app/Console/Commands/ResetGoshenDemoWalletVoucherData.php`
- Create: `tests/Feature/GoshenDemoWalletVoucherResetCommandTest.php`

**Interfaces:**
- Consumes: `goshen_voucher_usages`, `goshen_vouchers`, `goshen_wallet_withdrawal_requests`, `goshen_wallet_savings_plans`, `goshen_wallet_goals`, `goshen_wallet_ledger_entries`, and `goshen_wallets`.
- Produces: `php artisan goshen:reset-demo-wallet-voucher-data --confirm=RESET-GOSHEN-DEMO-WALLET-VOUCHER-DATA`, which prints counts and does not change identity or non-wallet financial tables.

- [ ] **Step 1: Write the failing reset-scope and confirmation tests**

Create a fixture containing a mobile user, wallet with a nonzero balance, ledger entry, goal, savings plan, withdrawal request, voucher usage, voucher, booking, ticket, and a non-wallet `PaymentTransaction`. Add tests that:

```php
$this->artisan('goshen:reset-demo-wallet-voucher-data')
    ->expectsOutput('Confirmation token required; no data was changed.')
    ->assertExitCode(1);

$this->artisan('goshen:reset-demo-wallet-voucher-data', [
    '--confirm' => 'RESET-GOSHEN-DEMO-WALLET-VOUCHER-DATA',
])->assertExitCode(0);

$this->assertDatabaseCount('goshen_vouchers', 0);
$this->assertDatabaseCount('goshen_voucher_usages', 0);
$this->assertDatabaseCount('goshen_wallet_ledger_entries', 0);
$this->assertDatabaseHas('goshen_wallets', ['id' => $wallet->id, 'balance' => 0]);
$this->assertDatabaseHas('mobile_users', ['id' => $member->id]);
$this->assertDatabaseHas('ei_bookings', ['id' => $booking->id]);
$this->assertDatabaseHas('ei_payment_transactions', ['id' => $ordinaryPayment->id]);
```

- [ ] **Step 2: Run the reset test to verify it fails**

Run:

```powershell
php artisan test tests/Feature/GoshenDemoWalletVoucherResetCommandTest.php
```

Expected: FAIL because the command does not exist.

- [ ] **Step 3: Implement count reporting, dry-run, and transactional deletion**

Set the command signature and guard exactly:

```php
protected $signature = 'goshen:reset-demo-wallet-voucher-data
    {--confirm= : Type RESET-GOSHEN-DEMO-WALLET-VOUCHER-DATA to perform the reset}
    {--dry-run : Print affected record counts without changing data}';

if ($this->option('confirm') !== 'RESET-GOSHEN-DEMO-WALLET-VOUCHER-DATA') {
    $this->error('Confirmation token required; no data was changed.');
    return self::FAILURE;
}
```

Collect and print counts before mutation. If `--dry-run` is present, return success without mutation. Otherwise execute one `DB::transaction()` in this order:

```php
DB::table('goshen_voucher_usages')->delete();
DB::table('goshen_vouchers')->delete();
DB::table('goshen_wallet_withdrawal_requests')->delete();
DB::table('goshen_wallet_savings_plans')->delete();
DB::table('goshen_wallet_goals')->delete();
DB::table('goshen_wallet_ledger_entries')->delete();
DB::table('goshen_wallets')->update([
    'balance' => 0,
    'goal_amount' => null,
    'goal_label' => null,
    'goal_target_at' => null,
    'updated_at' => now(),
]);
```

Print post-reset counts for each affected table. Do not alter Stripe fields, `mobile_users`, `users`, `ei_bookings`, `ei_tickets`, or `ei_payment_transactions`.

- [ ] **Step 4: Run focused command, migration, and formatter checks**

Run:

```powershell
php artisan test tests/Feature/GoshenDemoWalletVoucherResetCommandTest.php
php artisan goshen:reset-demo-wallet-voucher-data --confirm=RESET-GOSHEN-DEMO-WALLET-VOUCHER-DATA --dry-run
php -l app/Console/Commands/ResetGoshenDemoWalletVoucherData.php
vendor\bin\pint --dirty
```

Expected: PASS. The dry run reports counts only; the test proves confirmed reset leaves identity and non-wallet payment data intact.

- [ ] **Step 5: Commit the cleanup command**

Run:

```powershell
git add app/Console/Commands/ResetGoshenDemoWalletVoucherData.php tests/Feature/GoshenDemoWalletVoucherResetCommandTest.php
git commit -m "Add guarded Goshen demo wallet reset"
```

## Task 6: Run the full verification, deploy Laravel, reset demo data, and build Flutter

**Files:**
- Verify: Laravel and Flutter files changed in Tasks 1–5.
- Output: `C:\Appbuild\Goshen-Flutter-App\build\app\outputs\flutter-apk\app-release.apk`.

**Interfaces:**
- Consumes: all prior commits and `scripts/deploy-release.sh` on both Goshen hosts.
- Produces: deployed Laravel purpose rules, confirmed-empty demo voucher/wallet financial state on both hosts, and a release APK containing the Control Hub selector.

- [ ] **Step 1: Run all changed-surface checks before pushing**

Run:

```powershell
php artisan test tests/Feature/GoshenVoucherPurposeTest.php tests/Feature/GoshenVoucherApiTest.php tests/Feature/GoshenVoucherAdminResourceTest.php tests/Feature/GoshenWalletVoucherWithdrawalTest.php tests/Feature/GoshenDemoWalletVoucherResetCommandTest.php tests/Feature/GoshenAdminTicketIssuanceTest.php
vendor\bin\pint --test
git diff --check
flutter test test/goshen_voucher_contract_test.dart
flutter analyze
```

Expected: all tests pass, Pint reports no required changes, `git diff --check` has no whitespace errors, and Flutter analysis is clean.

- [ ] **Step 2: Commit any final test-only adjustments and push both repositories**

Run:

```powershell
git -C C:\Appbuild\Goshen-Laravel-Admin-Staging status --short
git -C C:\Appbuild\Goshen-Laravel-Admin-Staging push origin main
git -C C:\Appbuild\Goshen-Flutter-App status --short
git -C C:\Appbuild\Goshen-Flutter-App push origin main
```

Expected: each `origin/main` SHA equals its local `HEAD`; do not stage unrelated untracked files.

- [ ] **Step 3: Deploy the Laravel commit to staging and verify migration presence**

Run:

```powershell
$sha = git -C C:\Appbuild\Goshen-Laravel-Admin-Staging rev-parse --short HEAD
ssh -o BatchMode=yes cels "bash /home/cels/projects/staging-goshen.shotfaz.com/scripts/deploy-release.sh staging $sha"
ssh -o BatchMode=yes cels 'cd /home/cels/projects/staging-goshen.shotfaz.com && php artisan migrate:status --path=database/migrations/2026_07_11_030000_add_purpose_to_goshen_vouchers.php'
```

Expected: staging points to `$sha` and the purpose migration is marked as run.

- [ ] **Step 4: Reset staging demo data and verify the zero state**

Run:

```powershell
ssh -o BatchMode=yes cels 'cd /home/cels/projects/staging-goshen.shotfaz.com && php artisan goshen:reset-demo-wallet-voucher-data --confirm=RESET-GOSHEN-DEMO-WALLET-VOUCHER-DATA --dry-run'
ssh -o BatchMode=yes cels 'cd /home/cels/projects/staging-goshen.shotfaz.com && php artisan goshen:reset-demo-wallet-voucher-data --confirm=RESET-GOSHEN-DEMO-WALLET-VOUCHER-DATA'
ssh -o BatchMode=yes cels 'cd /home/cels/projects/staging-goshen.shotfaz.com && php artisan goshen:reset-demo-wallet-voucher-data --confirm=RESET-GOSHEN-DEMO-WALLET-VOUCHER-DATA --dry-run'
```

Expected: the final dry run reports zero voucher usages, vouchers, wallet ledger entries, withdrawals, savings plans, and wallet goals; retained wallets all have balance zero.

- [ ] **Step 5: Validate staging behaviour before production**

Use an authorised admin to create one **Wallet Funding** code and one **For Payments** code. Verify the first code works only in a member wallet top-up and the second works only in an eligible full payment. Confirm the voucher table shows Purpose, selection checkboxes, unused delete, bulk delete, and bulk void; confirm a redeemed code has no delete/void action.

Expected: every mismatch is rejected without a changed wallet balance, booking state, or voucher use count.

- [ ] **Step 6: Deploy production, reset confirmed demo data, and re-check the live release**

Run:

```powershell
$sha = git -C C:\Appbuild\Goshen-Laravel-Admin-Staging rev-parse --short HEAD
ssh -o BatchMode=yes cels "bash /home/cels/projects/goshen.shotfaz.com/scripts/deploy-release.sh production $sha"
ssh -o BatchMode=yes cels 'cd /home/cels/projects/goshen.shotfaz.com && php artisan goshen:reset-demo-wallet-voucher-data --confirm=RESET-GOSHEN-DEMO-WALLET-VOUCHER-DATA --dry-run'
ssh -o BatchMode=yes cels 'cd /home/cels/projects/goshen.shotfaz.com && php artisan goshen:reset-demo-wallet-voucher-data --confirm=RESET-GOSHEN-DEMO-WALLET-VOUCHER-DATA'
ssh -o BatchMode=yes cels 'readlink -f /home/cels/projects/staging-goshen.shotfaz.com; readlink -f /home/cels/projects/goshen.shotfaz.com'
```

Expected: both current-release links include `$sha`; production reset output reports the deleted demo counts and no credentials or voucher codes.

- [ ] **Step 7: Build and verify the Flutter release APK**

Run:

```powershell
flutter build apk --release
Get-FileHash build\app\outputs\flutter-apk\app-release.apk -Algorithm SHA256
```

Expected: a signed release APK is produced. Do not install, distribute, or publish it without a separate user request.

- [ ] **Step 8: Record final verification evidence**

Capture local/remote commit SHAs, Laravel test summaries, Flutter test/analyse summaries, both release symlink targets, reset post-counts, and APK SHA-256 in the completion report. Report only aggregate counts and commit identifiers.
