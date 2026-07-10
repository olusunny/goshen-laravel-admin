<?php

namespace Tests\Feature;

use App\Filament\Pages\GoshenRetreatConsole;
use App\Filament\Resources\GoshenTicketResource;
use App\Filament\Resources\GoshenTicketResource\Pages\CreateGoshenTicket;
use App\Models\GoshenWallet;
use App\Models\MobileUser;
use App\Models\User;
use App\Models\WebWalletVerificationChallenge;
use App\Services\DynamicSmtpMailer;
use App\Services\GoshenAdminTicketIssuanceService;
use App\Services\GoshenVoucherService;
use App\Services\LinkedMobileAccountService;
use App\Services\WebWalletVerificationService;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery\MockInterface;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\BookingLine;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventAuditLog;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Models\Ticket;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoshenAdminTicketIssuanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_goshen_tickets_expose_an_admin_creation_page(): void
    {
        $this->assertArrayHasKey('create', GoshenTicketResource::getPages());
    }

    public function test_admin_ticket_issuance_has_a_dedicated_domain_service(): void
    {
        $this->assertTrue(class_exists(GoshenAdminTicketIssuanceService::class));
    }

    public function test_ticket_issuance_permission_is_available_to_admin_roles(): void
    {
        $this->assertArrayHasKey('goshen_ticket.issue', AdminPermissions::all());
    }

    public function test_admin_can_issue_a_paid_ticket_with_a_voucher(): void
    {
        [$member, $ticketType, $admin] = $this->issuanceFixture();
        $voucher = app(GoshenVoucherService::class)->createVoucher([
            'event_id' => $ticketType->event_id,
            'currency' => 'GBP',
            'amount' => 150,
            'max_uses' => 1,
        ], adminActor: $admin);

        $ticket = app(GoshenAdminTicketIssuanceService::class)->issue(
            $member,
            $ticketType,
            $admin,
            'Front desk registration',
            'voucher',
            $voucher['code'],
        );

        $booking = $ticket->booking()->with(['lines', 'attendees', 'installments.transactions'])->firstOrFail();

        $this->assertSame($member->id, $booking->customer_id);
        $this->assertSame(BookingStatus::Paid, $booking->status);
        $this->assertSame('150.00', $booking->subtotal);
        $this->assertSame('150.00', $booking->total);
        $this->assertSame('150.00', $booking->paid_total);
        $this->assertNull($booking->payment_plan_id);
        $this->assertSame('filament_admin', $booking->metadata['source']);
        $this->assertSame('voucher', $booking->metadata['payment_method']);
        $this->assertArrayNotHasKey('complimentary', $booking->metadata);
        $this->assertArrayNotHasKey('voucher_code', $booking->metadata);
        $this->assertSame($admin->id, $booking->metadata['issued_by_admin_id']);
        $this->assertSame('Front desk registration', $booking->metadata['issuance_reason']);
        $this->assertCount(1, $booking->lines);
        $this->assertSame('150.00', $booking->lines->first()->unit_price);
        $this->assertSame('150.00', $booking->lines->first()->line_total);
        $this->assertCount(1, $booking->installments);
        $this->assertSame('150.00', $booking->installments->first()->amount);
        $this->assertSame('150.00', $booking->installments->first()->paid_amount);
        $this->assertCount(1, $booking->installments->first()->transactions);
        $this->assertSame('voucher', $booking->installments->first()->transactions->first()->gateway);
        $this->assertCount(1, $booking->attendees);
        $this->assertSame($member->email, $booking->attendees->first()->email);
        $this->assertSame($booking->attendees->first()->id, $ticket->attendee_id);
        $this->assertSame(TicketStatus::NotCheckedIn, $ticket->status);
        $this->assertNotEmpty($ticket->formatted_number);
        $this->assertNotEmpty($ticket->qr_hash);
        $this->assertSame('voucher', data_get($ticket->metadata, 'payment_method'));
        $this->assertSame($member->id, data_get($ticket->metadata, 'beneficiary_mobile_user_id'));
        $this->assertNotEmpty(data_get($ticket->metadata, 'voucher_usage_id'));
        $this->assertNotEmpty(data_get($ticket->metadata, 'payment_transaction_id'));
        $this->assertNotEmpty(data_get($ticket->metadata, 'payment_reference'));
        $this->assertStringNotContainsString(
            $voucher['code'],
            json_encode([$booking->metadata, $ticket->metadata], JSON_THROW_ON_ERROR),
        );
        $this->assertDatabaseHas('goshen_voucher_usages', [
            'booking_id' => $booking->id,
            'mobile_user_id' => $member->id,
            'amount' => 150,
            'status' => 'applied',
        ]);
        $this->assertDatabaseHas((new PaymentTransaction)->getTable(), [
            'booking_id' => $booking->id,
            'installment_id' => $booking->installments->first()->id,
            'gateway' => 'voucher',
            'amount' => 150,
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas(EventAuditLog::class, [
            'event_id' => $ticketType->event_id,
            'actor_id' => $admin->id,
            'action' => 'admin_ticket_issued',
            'auditable_type' => $ticket::class,
            'auditable_id' => $ticket->id,
        ]);
    }

    public function test_admin_ticket_issuance_rejects_a_zero_price_without_creating_financial_records(): void
    {
        [$member, $ticketType, $admin] = $this->issuanceFixture();
        $ticketType->forceFill(['price' => 0])->save();

        try {
            app(GoshenAdminTicketIssuanceService::class)->issue(
                $member,
                $ticketType,
                $admin,
                'Front desk registration',
                'voucher',
                'GSH-NOT-A-REAL-VOUCHER',
            );
            $this->fail('Expected a zero-price validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ticket_type_id', $exception->errors());
        }

        $this->assertDatabaseCount((new PaymentInstallment)->getTable(), 0);
        $this->assertDatabaseCount((new PaymentTransaction)->getTable(), 0);
        $this->assertDatabaseCount('ei_bookings', 0);
        $this->assertDatabaseCount('ei_tickets', 0);
    }

    public function test_admin_can_pay_for_a_member_ticket_from_only_their_linked_wallet(): void
    {
        [$member, $ticketType, $admin, $payer, $wallet, $challenge, $code] = $this->walletIssuanceFixture();

        $ticket = app(GoshenAdminTicketIssuanceService::class)->issue(
            $member,
            $ticketType,
            $admin,
            'Front desk registration',
            'wallet',
            null,
            $challenge,
            $code,
            '127.0.0.1',
            'PHPUnit',
        );

        $booking = $ticket->booking()->with('installments.transactions')->firstOrFail();
        $transaction = $booking->installments->firstOrFail()->transactions->firstOrFail();

        $this->assertSame('350.00', $wallet->fresh()->balance);
        $this->assertSame(BookingStatus::Paid, $booking->status);
        $this->assertSame('150.00', $booking->total);
        $this->assertSame('150.00', $booking->paid_total);
        $this->assertSame('wallet', $transaction->gateway);
        $this->assertSame('paid', $transaction->status);
        $this->assertSame($wallet->id, data_get($transaction->payload, 'wallet_id'));
        $this->assertSame($payer->id, data_get($transaction->payload, 'payer_mobile_user_id'));
        $this->assertSame($member->id, data_get($transaction->payload, 'beneficiary_mobile_user_id'));
        $this->assertSame($admin->id, data_get($transaction->payload, 'payer_admin_user_id'));
        $this->assertSame('wallet', data_get($ticket->metadata, 'payment_method'));
        $this->assertSame($payer->id, data_get($ticket->metadata, 'payer_mobile_user_id'));
        $this->assertNotEmpty(data_get($ticket->metadata, 'payment_transaction_id'));
        $this->assertStringNotContainsString(
            $code,
            json_encode([$booking->metadata, $ticket->metadata, $transaction->payload], JSON_THROW_ON_ERROR),
        );
        $this->assertDatabaseHas('goshen_wallet_ledger_entries', [
            'wallet_id' => $wallet->id,
            'type' => 'retreat_payment',
            'status' => 'paid',
            'currency' => 'GBP',
            'amount' => 150,
            'gateway' => 'wallet',
        ]);
        $this->assertSame('consumed', $challenge->fresh()->status);
        $this->assertArrayNotHasKey('wallet_otp', $booking->metadata ?? []);
    }

    public function test_invalid_wallet_code_leaves_no_financial_records(): void
    {
        [$member, $ticketType, $admin, $payer, $wallet, $challenge, $code] = $this->walletIssuanceFixture();
        $wrongCode = $code === '000000' ? '999999' : '000000';

        try {
            app(GoshenAdminTicketIssuanceService::class)->issue(
                $member,
                $ticketType,
                $admin,
                'Front desk registration',
                'wallet',
                null,
                $challenge,
                $wrongCode,
                '127.0.0.1',
                'PHPUnit',
            );
            $this->fail('Expected wallet verification failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('wallet_otp', $exception->errors());
        }

        $this->assertSame('500.00', $wallet->fresh()->balance);
        $this->assertDatabaseCount('ei_bookings', 0);
        $this->assertDatabaseCount((new PaymentInstallment)->getTable(), 0);
        $this->assertDatabaseCount((new PaymentTransaction)->getTable(), 0);
        $this->assertDatabaseCount('goshen_wallet_ledger_entries', 0);
        $this->assertDatabaseCount('ei_tickets', 0);
        $this->assertSame(1, $challenge->fresh()->attempts);
    }

    public function test_invalid_voucher_rolls_back_all_registration_and_payment_records(): void
    {
        [$member, $ticketType, $admin] = $this->issuanceFixture();

        try {
            app(GoshenAdminTicketIssuanceService::class)->issue(
                $member,
                $ticketType,
                $admin,
                'Front desk registration',
                'voucher',
                'GSH-INVALID-VOUCHER',
            );
            $this->fail('Expected voucher validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('voucher_code', $exception->errors());
        }

        $this->assertDatabaseCount('ei_bookings', 0);
        $this->assertDatabaseCount((new PaymentInstallment)->getTable(), 0);
        $this->assertDatabaseCount((new PaymentTransaction)->getTable(), 0);
        $this->assertDatabaseCount('goshen_voucher_usages', 0);
        $this->assertDatabaseCount('ei_tickets', 0);
    }

    public function test_insufficient_wallet_balance_consumes_the_code_but_rolls_back_financial_records(): void
    {
        [$member, $ticketType, $admin, $payer, $wallet, $challenge, $code] = $this->walletIssuanceFixture(balance: 149);

        try {
            app(GoshenAdminTicketIssuanceService::class)->issue(
                $member,
                $ticketType,
                $admin,
                'Front desk registration',
                'wallet',
                null,
                $challenge,
                $code,
                '127.0.0.1',
                'PHPUnit',
            );
            $this->fail('Expected insufficient wallet balance failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_method', $exception->errors());
        }

        $this->assertSame('149.00', $wallet->fresh()->balance);
        $this->assertSame('consumed', $challenge->fresh()->status);
        $this->assertDatabaseCount('ei_bookings', 0);
        $this->assertDatabaseCount((new PaymentTransaction)->getTable(), 0);
        $this->assertDatabaseCount('goshen_wallet_ledger_entries', 0);
        $this->assertDatabaseCount('ei_tickets', 0);
    }

    public function test_wrong_currency_wallet_consumes_the_code_but_rolls_back_financial_records(): void
    {
        [$member, $ticketType, $admin, $payer, $wallet, $challenge, $code] = $this->walletIssuanceFixture(currency: 'USD');

        try {
            app(GoshenAdminTicketIssuanceService::class)->issue(
                $member,
                $ticketType,
                $admin,
                'Front desk registration',
                'wallet',
                null,
                $challenge,
                $code,
                '127.0.0.1',
                'PHPUnit',
            );
            $this->fail('Expected wallet currency validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_method', $exception->errors());
        }

        $this->assertSame('500.00', $wallet->fresh()->balance);
        $this->assertSame('consumed', $challenge->fresh()->status);
        $this->assertDatabaseCount('ei_bookings', 0);
        $this->assertDatabaseCount((new PaymentTransaction)->getTable(), 0);
        $this->assertDatabaseCount('goshen_wallet_ledger_entries', 0);
        $this->assertDatabaseCount('ei_tickets', 0);
    }

    public function test_blocked_linked_payer_is_rejected_before_the_wallet_code_is_consumed(): void
    {
        [$member, $ticketType, $admin, $payer, $wallet, $challenge, $code] = $this->walletIssuanceFixture();
        $payer->forceFill(['is_blocked' => true])->save();

        try {
            app(GoshenAdminTicketIssuanceService::class)->issue(
                $member,
                $ticketType,
                $admin,
                'Front desk registration',
                'wallet',
                null,
                $challenge,
                $code,
                '127.0.0.1',
                'PHPUnit',
            );
            $this->fail('Expected linked payer validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_method', $exception->errors());
        }

        $this->assertSame('pending', $challenge->fresh()->status);
        $this->assertSame('500.00', $wallet->fresh()->balance);
        $this->assertDatabaseCount('ei_bookings', 0);
    }

    public function test_wallet_security_reset_restriction_is_rejected_before_code_consumption(): void
    {
        [$member, $ticketType, $admin, $payer, $wallet, $challenge, $code] = $this->walletIssuanceFixture();
        $payer->forceFill(['wallet_security_reset_required' => true])->save();

        try {
            app(GoshenAdminTicketIssuanceService::class)->issue(
                $member,
                $ticketType,
                $admin,
                'Front desk registration',
                'wallet',
                null,
                $challenge,
                $code,
                '127.0.0.1',
                'PHPUnit',
            );
            $this->fail('Expected wallet security restriction failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_method', $exception->errors());
        }

        $this->assertSame('pending', $challenge->fresh()->status);
        $this->assertSame('500.00', $wallet->fresh()->balance);
        $this->assertDatabaseCount('ei_bookings', 0);
    }

    public function test_expired_wallet_code_never_creates_financial_records(): void
    {
        [$member, $ticketType, $admin, $payer, $wallet, $challenge, $code] = $this->walletIssuanceFixture();
        $challenge->forceFill(['expires_at' => now()->subSecond()])->save();

        try {
            app(GoshenAdminTicketIssuanceService::class)->issue(
                $member, $ticketType, $admin, 'Front desk registration', 'wallet', null,
                $challenge, $code, '127.0.0.1', 'PHPUnit',
            );
            $this->fail('Expected expired wallet challenge failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('wallet_otp', $exception->errors());
        }

        $this->assertSame('expired', $challenge->fresh()->status);
        $this->assertSame('500.00', $wallet->fresh()->balance);
        $this->assertDatabaseCount('ei_bookings', 0);
        $this->assertDatabaseCount('goshen_wallet_ledger_entries', 0);
    }

    public function test_wallet_code_bound_to_another_reason_is_rejected_without_financial_records(): void
    {
        [$member, $ticketType, $admin, $payer, $wallet, $challenge, $code] = $this->walletIssuanceFixture();

        try {
            app(GoshenAdminTicketIssuanceService::class)->issue(
                $member, $ticketType, $admin, 'Changed issuance reason', 'wallet', null,
                $challenge, $code, '127.0.0.1', 'PHPUnit',
            );
            $this->fail('Expected request-bound wallet challenge failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('wallet_otp', $exception->errors());
        }

        $this->assertSame('pending', $challenge->fresh()->status);
        $this->assertSame(1, $challenge->fresh()->attempts);
        $this->assertSame('500.00', $wallet->fresh()->balance);
        $this->assertDatabaseCount('ei_bookings', 0);
    }

    public function test_consumed_wallet_code_cannot_issue_a_second_ticket(): void
    {
        [$member, $ticketType, $admin, $payer, $wallet, $challenge, $code] = $this->walletIssuanceFixture();
        app(GoshenAdminTicketIssuanceService::class)->issue(
            $member, $ticketType, $admin, 'Front desk registration', 'wallet', null,
            $challenge, $code, '127.0.0.1', 'PHPUnit',
        );

        $otherMember = MobileUser::query()->create([
            'name' => 'Grace Hopper',
            'email' => 'grace.hopper@example.test',
            'is_verified' => true,
        ]);

        try {
            app(GoshenAdminTicketIssuanceService::class)->issue(
                $otherMember, $ticketType, $admin, 'Front desk registration', 'wallet', null,
                $challenge->fresh(), $code, '127.0.0.1', 'PHPUnit',
            );
            $this->fail('Expected consumed wallet challenge failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('wallet_otp', $exception->errors());
        }

        $this->assertSame('consumed', $challenge->fresh()->status);
        $this->assertSame('350.00', $wallet->fresh()->balance);
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(0, Booking::query()->where('customer_id', $otherMember->id)->count());
    }

    #[DataProvider('walletContextMutations')]
    public function test_wallet_context_change_after_otp_consumption_rolls_back_finance(
        string $mutation,
    ): void {
        [$member, $ticketType, $admin, $payer, $wallet, $challenge, $code] = $this->walletIssuanceFixture();
        $replacementEvent = Event::query()->create([
            'name' => 'Replacement Goshen',
            'slug' => 'replacement-goshen-'.$mutation,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
        ]);
        $mutated = false;

        EventFacade::listen(
            'eloquent.updated: '.WebWalletVerificationChallenge::class,
            function (WebWalletVerificationChallenge $updated) use (
                &$mutated,
                $mutation,
                $member,
                $ticketType,
                $replacementEvent,
            ): void {
                if ($mutated || $updated->status !== 'consumed') {
                    return;
                }

                $mutated = true;
                match ($mutation) {
                    'price' => $ticketType->forceFill(['price' => 175])->save(),
                    'currency' => $ticketType->forceFill(['currency' => 'USD'])->save(),
                    'event' => $ticketType->forceFill(['event_id' => $replacementEvent->id])->save(),
                    'member' => $member->forceFill(['email' => 'changed-member@example.test'])->save(),
                };
            },
        );

        try {
            app(GoshenAdminTicketIssuanceService::class)->issue(
                $member, $ticketType, $admin, 'Front desk registration', 'wallet', null,
                $challenge, $code, '127.0.0.1', 'PHPUnit',
            );
            $this->fail('Expected stale wallet authorization context failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('wallet_otp', $exception->errors());
        }

        $this->assertTrue($mutated);
        $this->assertSame('consumed', $challenge->fresh()->status);
        $this->assertSame('500.00', $wallet->fresh()->balance);
        $this->assertDatabaseCount('ei_bookings', 0);
        $this->assertDatabaseCount((new PaymentTransaction)->getTable(), 0);
        $this->assertDatabaseCount('goshen_wallet_ledger_entries', 0);
        $this->assertDatabaseCount('ei_tickets', 0);
    }

    public static function walletContextMutations(): array
    {
        return [
            'price changed' => ['price'],
            'currency changed' => ['currency'],
            'event or ticket context changed' => ['event'],
            'member context changed' => ['member'],
        ];
    }

    public function test_admin_email_change_after_otp_consumption_cannot_debit_linked_wallet(): void
    {
        [$member, $ticketType, $admin, $payer, $wallet, $challenge, $code] = $this->walletIssuanceFixture();
        $mutated = false;
        EventFacade::listen(
            'eloquent.updated: '.WebWalletVerificationChallenge::class,
            function (WebWalletVerificationChallenge $updated) use (&$mutated, $admin): void {
                if ($mutated || $updated->status !== 'consumed') {
                    return;
                }

                $mutated = true;
                $admin->forceFill(['email' => 'changed-admin@example.test'])->save();
            },
        );

        try {
            app(GoshenAdminTicketIssuanceService::class)->issue(
                $member, $ticketType, $admin, 'Front desk registration', 'wallet', null,
                $challenge, $code, '127.0.0.1', 'PHPUnit',
            );
            $this->fail('Expected changed admin identity failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment_method', $exception->errors());
        }

        $this->assertTrue($mutated);
        $this->assertSame('consumed', $challenge->fresh()->status);
        $this->assertSame('500.00', $wallet->fresh()->balance);
        $this->assertDatabaseCount('ei_bookings', 0);
        $this->assertDatabaseCount('goshen_wallet_ledger_entries', 0);
    }

    public function test_pending_booking_line_prevents_duplicate_admin_issuance(): void
    {
        [$member, $ticketType, $admin] = $this->issuanceFixture();
        $this->reservation($member, $ticketType, 1, BookingStatus::Pending);

        try {
            app(GoshenAdminTicketIssuanceService::class)->issue(
                $member,
                $ticketType,
                $admin,
                'Duplicate front desk registration',
                'voucher',
                $this->voucherCode($ticketType, $admin),
            );
            $this->fail('Expected pending registration duplicate failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customer_id', $exception->errors());
        }

        $this->assertSame(1, Booking::query()->count());
        $this->assertDatabaseCount('goshen_voucher_usages', 0);
        $this->assertDatabaseCount('ei_tickets', 0);
    }

    public function test_sold_out_ticket_rejects_wallet_before_otp_consumption(): void
    {
        [$member, $ticketType, $admin, $payer, $wallet, $challenge, $code] = $this->walletIssuanceFixture();
        $ticketType->forceFill(['capacity' => 1])->save();
        $other = MobileUser::query()->create([
            'name' => 'Reserved Member',
            'email' => 'reserved@example.test',
            'is_verified' => true,
        ]);
        $this->reservation($other, $ticketType, 1, BookingStatus::Pending);

        try {
            app(GoshenAdminTicketIssuanceService::class)->issue(
                $member, $ticketType, $admin, 'Front desk registration', 'wallet', null,
                $challenge, $code, '127.0.0.1', 'PHPUnit',
            );
            $this->fail('Expected sold-out ticket validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ticket_type_id', $exception->errors());
        }

        $this->assertSame('pending', $challenge->fresh()->status);
        $this->assertSame('500.00', $wallet->fresh()->balance);
        $this->assertSame(1, Booking::query()->count());
        $this->assertDatabaseCount('goshen_wallet_ledger_entries', 0);
    }

    public function test_sold_out_ticket_rejects_voucher_without_consuming_it(): void
    {
        [$member, $ticketType, $admin] = $this->issuanceFixture();
        $ticketType->forceFill(['capacity' => 1])->save();
        $other = MobileUser::query()->create([
            'name' => 'Voucher Reserved Member',
            'email' => 'voucher-reserved@example.test',
            'is_verified' => true,
        ]);
        $this->reservation($other, $ticketType, 1, BookingStatus::Pending);
        $code = $this->voucherCode($ticketType, $admin);

        try {
            app(GoshenAdminTicketIssuanceService::class)->issue(
                $member,
                $ticketType,
                $admin,
                'Sold out voucher registration',
                'voucher',
                $code,
            );
            $this->fail('Expected sold-out ticket validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ticket_type_id', $exception->errors());
        }

        $this->assertSame(1, Booking::query()->count());
        $this->assertDatabaseCount('goshen_voucher_usages', 0);
        $this->assertDatabaseCount((new PaymentTransaction)->getTable(), 0);
    }

    public function test_admin_cannot_issue_a_duplicate_ticket_for_the_same_member_event_and_type(): void
    {
        [$member, $ticketType, $admin] = $this->issuanceFixture();
        $service = app(GoshenAdminTicketIssuanceService::class);

        $service->issue(
            $member,
            $ticketType,
            $admin,
            'First allocation',
            'voucher',
            $this->voucherCode($ticketType, $admin),
        );

        $this->expectException(ValidationException::class);
        $service->issue(
            $member,
            $ticketType,
            $admin,
            'Duplicate allocation',
            'voucher',
            $this->voucherCode($ticketType, $admin),
        );
    }

    public function test_admin_cannot_issue_a_ticket_to_a_blocked_member(): void
    {
        [$member, $ticketType, $admin] = $this->issuanceFixture();
        $member->forceFill(['is_blocked' => true])->save();

        $this->expectException(ValidationException::class);

        app(GoshenAdminTicketIssuanceService::class)->issue(
            $member,
            $ticketType,
            $admin,
            'Blocked member allocation',
            'voucher',
            'GSH-UNUSED-CODE',
        );
    }

    public function test_admin_cannot_issue_a_ticket_for_an_unpublished_retreat(): void
    {
        [$member, $ticketType, $admin] = $this->issuanceFixture();
        $ticketType->event->forceFill(['status' => 'draft'])->save();

        $this->expectException(ValidationException::class);

        app(GoshenAdminTicketIssuanceService::class)->issue(
            $member,
            $ticketType,
            $admin,
            'Draft retreat allocation',
            'voucher',
            'GSH-UNUSED-CODE',
        );
    }

    public function test_any_admin_role_can_be_granted_ticket_issuance_without_delete_access(): void
    {
        $permission = Permission::findOrCreate(AdminPermissions::GOSHEN_TICKET_ISSUE, 'web');
        $role = Role::findOrCreate('ticket_desk', 'web');
        $role->givePermissionTo($permission);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        $this->actingAs($admin);

        $this->assertFalse(GoshenTicketResource::canViewAny());
        $this->assertTrue(GoshenTicketResource::canCreate());
        $this->assertFalse(GoshenTicketResource::canView(new Ticket));
        $this->assertFalse(GoshenTicketResource::canDelete(new Ticket));
        $this->assertTrue(GoshenRetreatConsole::canAccess());

        $ticketCard = collect((new GoshenRetreatConsole)->getViewData()['cards'])
            ->firstWhere('title', 'Tickets');

        $this->assertSame(GoshenTicketResource::getUrl('create'), $ticketCard['url']);
    }

    public function test_admin_without_ticket_permissions_cannot_open_the_issuance_page(): void
    {
        $permission = Permission::findOrCreate('manage_goshen_booking', 'web');
        $role = Role::findOrCreate('booking_desk', 'web');
        $role->givePermissionTo($permission);
        $admin = User::factory()->create();
        $admin->assignRole($role);

        Livewire::actingAs($admin)
            ->test(CreateGoshenTicket::class)
            ->assertStatus(403);
    }

    public function test_issue_only_admin_cannot_open_existing_ticket_details(): void
    {
        [$member, $ticketType, $admin] = $this->issuanceFixture();
        $ticket = app(GoshenAdminTicketIssuanceService::class)->issue(
            $member,
            $ticketType,
            $admin,
            'Private ticket allocation',
            'voucher',
            $this->voucherCode($ticketType, $admin),
        );
        $permission = Permission::findOrCreate(AdminPermissions::GOSHEN_TICKET_ISSUE, 'web');
        $role = Role::findOrCreate('ticket_desk', 'web');
        $role->givePermissionTo($permission);
        $admin->assignRole($role);

        $this->actingAs($admin)
            ->get(GoshenTicketResource::getUrl('view', ['record' => $ticket]))
            ->assertForbidden();
    }

    /**
     * @return array{MobileUser, EventTicketType, User}
     */
    private function issuanceFixture(): array
    {
        $member = MobileUser::query()->create([
            'name' => 'Ada Lovelace',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'phone' => '+2348012345678',
            'is_verified' => true,
        ]);

        $event = Event::query()->create([
            'name' => 'Goshen 2026',
            'slug' => 'goshen-2026',
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
        ]);

        $ticketType = EventTicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Adult',
            'currency' => 'GBP',
            'price' => 150,
            'capacity' => 100,
            'is_active' => true,
        ]);

        $admin = User::factory()->create();

        return [$member, $ticketType, $admin];
    }

    private function voucherCode(EventTicketType $ticketType, User $admin): string
    {
        return app(GoshenVoucherService::class)->createVoucher([
            'event_id' => $ticketType->event_id,
            'currency' => $ticketType->currency,
            'amount' => $ticketType->price,
            'max_uses' => 1,
        ], adminActor: $admin)['code'];
    }

    private function reservation(
        MobileUser $member,
        EventTicketType $ticketType,
        int $quantity,
        BookingStatus $status,
    ): Booking {
        $booking = Booking::query()->create([
            'event_id' => $ticketType->event_id,
            'customer_id' => $member->id,
            'customer_name' => $member->name,
            'customer_email' => $member->email,
            'currency' => $ticketType->currency,
            'subtotal' => (float) $ticketType->price * $quantity,
            'total' => (float) $ticketType->price * $quantity,
            'paid_total' => 0,
            'status' => $status,
        ]);
        BookingLine::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => $quantity,
            'currency' => $ticketType->currency,
            'unit_price' => $ticketType->price,
            'line_total' => (float) $ticketType->price * $quantity,
        ]);

        return $booking;
    }

    /**
     * @return array{MobileUser, EventTicketType, User, MobileUser, GoshenWallet, WebWalletVerificationChallenge, string}
     */
    private function walletIssuanceFixture(float $balance = 500, string $currency = 'GBP'): array
    {
        [$member, $ticketType, $admin] = $this->issuanceFixture();
        $payer = app(LinkedMobileAccountService::class)->forAdmin($admin);
        $this->assertInstanceOf(MobileUser::class, $payer);
        $wallet = $payer->wallet()->firstOrFail();
        $wallet->forceFill(['balance' => $balance, 'currency' => $currency])->save();

        $sentBody = null;
        $this->mock(DynamicSmtpMailer::class, function (MockInterface $mock) use (&$sentBody): void {
            $mock->shouldReceive('sendRaw')->once()->andReturnUsing(
                function (string $to, string $subject, string $body) use (&$sentBody): void {
                    $sentBody = $body;
                },
            );
        });

        $issuer = app(GoshenAdminTicketIssuanceService::class);
        $context = $issuer->verificationContext($member, $ticketType, 'Front desk registration');
        $challenge = app(WebWalletVerificationService::class)->issue(
            $admin,
            $payer,
            'admin_ticket_issue',
            $context,
            '127.0.0.1',
            'PHPUnit',
        );
        preg_match('/\b(\d{6})\b/', (string) $sentBody, $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        return [$member, $ticketType, $admin, $payer, $wallet, $challenge, $matches[1]];
    }
}
