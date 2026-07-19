<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\GoshenWallet;
use App\Models\MobileUser;
use App\Services\DynamicSmtpMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Mail\TicketIssuedMail;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\BookingLine;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventSchedule;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Models\TicketEmailLog;
use Tests\TestCase;

class GoshenWalletTicketEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_registration_sends_pdf_ticket_email_after_ticket_issue(): void
    {
        $this->configureTicketEmailTest();
        $member = $this->verifiedMember('wallet-registration@example.test', 'Wallet Registration');
        $token = $member->issueApiToken();
        [$event, $ticketType] = $this->publishedRetreatEvent(price: 300);
        $this->wallet($member, 500);

        $this->postJson('/api/goshen-retreat/bookings', [
            'data' => $this->bookingPayload($token, $event, $ticketType, [
                'payment_mode' => 'wallet',
            ]),
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('booking.status', BookingStatus::Paid->value)
            ->assertJsonCount(1, 'booking.tickets');

        $booking = Booking::query()->where('customer_id', $member->id)->firstOrFail();
        $ticket = Ticket::query()->where('booking_id', $booking->id)->firstOrFail();
        $log = TicketEmailLog::query()->where('ticket_id', $ticket->id)->latest('id')->firstOrFail();

        $this->assertSame('sent', $log->status);
        $this->assertSame($member->email, $log->recipient);
        $this->assertTrue($this->logHasPdfAttachment($log));
        Mail::assertSent(TicketIssuedMail::class, 1);
    }

    public function test_existing_booking_wallet_payment_sends_pdf_ticket_email_after_ticket_issue(): void
    {
        $this->configureTicketEmailTest();
        $member = $this->verifiedMember('wallet-existing@example.test', 'Wallet Existing');
        $token = $member->issueApiToken();
        [$booking] = $this->pendingBookingWithFullPayment($member);
        $this->wallet($member, 500);

        $this->postJson("/api/goshen-retreat/bookings/{$booking->public_id}/wallet-pay", [
            'data' => ['api_token' => $token],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('booking.status', BookingStatus::Paid->value)
            ->assertJsonCount(1, 'booking.tickets');

        $ticket = Ticket::query()->where('booking_id', $booking->id)->firstOrFail();
        $log = TicketEmailLog::query()->where('ticket_id', $ticket->id)->latest('id')->firstOrFail();

        $this->assertSame('sent', $log->status);
        $this->assertSame($member->email, $log->recipient);
        $this->assertTrue($this->logHasPdfAttachment($log));
        Mail::assertSent(TicketIssuedMail::class, 1);
    }

    private function configureTicketEmailTest(): void
    {
        Storage::fake('local');
        Mail::fake();

        Config::set('event-installments.storage.disk', 'local');
        Config::set('event-installments.ticket.qr_secret', 'wallet-ticket-email-test-secret');
        Config::set('event-installments.ticket.email.enabled', true);
        Config::set('event-installments.ticket.email.attach_pdf', true);
        Config::set('event-installments.ticket.email.attach_ics', false);

        $this->mock(DynamicSmtpMailer::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMailable')
                ->once()
                ->andReturnUsing(function (string $to, TicketIssuedMail $mail): void {
                    Mail::to($to)->send($mail);
                });
        });
    }

    private function bookingPayload(string $token, Event $event, EventTicketType $ticketType, array $overrides = []): array
    {
        return array_merge([
            'api_token' => $token,
            'event_id' => $event->public_id,
            'ticket_type_id' => $ticketType->public_id,
            'quantity' => 1,
            'uk_privacy_consent' => true,
            'privacy_policy_version' => 'uk-gdpr-2026-06',
            'attendees' => [[
                'title' => 'Mr.',
                'first_name' => 'Wallet',
                'last_name' => 'Member',
                'designation' => 'member',
                'gender' => 'male',
                'marital_status' => 'Married',
                'age_group' => 'adult',
                'free_church_bus_interest' => 'no_thanks',
                'volunteer_department' => 'no_chance_at_the_moment',
            ]],
        ], $overrides);
    }

    private function verifiedMember(
        string $email,
        string $name,
        string $phone = '+2348011112222',
    ): MobileUser {
        AppSetting::query()->updateOrCreate(
            ['key' => 'goshen_retreat_enabled'],
            ['group' => 'modules', 'value' => '1', 'is_secret' => false],
        );

        return MobileUser::query()->create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => 'secret',
            'title' => 'Mr.',
            'gender' => 'male',
            'marital_status' => 'Married',
            'member_type' => 'church_member',
            'country_of_residence' => 'Nigeria',
            'state_county_province' => 'Lagos',
            'address' => '1 Mercy Road, Lagos',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * @return array{0: Event, 1: EventTicketType}
     */
    private function publishedRetreatEvent(float $price = 300): array
    {
        $event = Event::query()->create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-retreat-2026-'.Str::random(8),
            'type' => EventType::Sequential,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addMonth(),
            'settings' => ['module' => 'goshen_retreat'],
        ]);

        EventSchedule::query()->create([
            'event_id' => $event->id,
            'day_number' => 1,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(4),
            'metadata' => ['title' => 'Opening service'],
        ]);

        $ticketType = EventTicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Adult',
            'sku' => 'ADULT',
            'currency' => 'NGN',
            'price' => $price,
            'min_per_booking' => 1,
            'max_per_booking' => 10,
            'is_active' => true,
        ]);

        return [$event->refresh(), $ticketType->refresh()];
    }

    /**
     * @return array{0: Booking, 1: PaymentInstallment}
     */
    private function pendingBookingWithFullPayment(MobileUser $member): array
    {
        [$event, $ticketType] = $this->publishedRetreatEvent(price: 300);

        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_id' => $member->id,
            'customer_name' => $member->name,
            'customer_email' => $member->email,
            'customer_phone' => $member->phone,
            'currency' => 'NGN',
            'subtotal' => 300,
            'total' => 300,
            'paid_total' => 0,
            'status' => BookingStatus::Pending,
            'payment_expires_at' => now()->addDay(),
        ]);

        BookingLine::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'currency' => 'NGN',
            'unit_price' => 300,
            'line_total' => 300,
        ]);

        Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => 'Wallet',
            'last_name' => 'Existing',
            'email' => $member->email,
            'phone' => $member->phone,
        ]);

        $installment = PaymentInstallment::query()->create([
            'booking_id' => $booking->id,
            'sequence' => 1,
            'currency' => 'NGN',
            'amount' => 300,
            'paid_amount' => 0,
            'due_on' => now()->addWeek()->toDateString(),
            'status' => InstallmentStatus::Pending,
        ]);

        return [$booking->refresh(), $installment->refresh()];
    }

    private function wallet(MobileUser $member, float $balance): GoshenWallet
    {
        return GoshenWallet::query()->updateOrCreate(
            ['mobile_user_id' => $member->id],
            [
                'currency' => 'NGN',
                'balance' => $balance,
            ],
        );
    }

    private function logHasPdfAttachment(TicketEmailLog $log): bool
    {
        return collect($log->attachments)->contains(
            fn (array $attachment): bool => ($attachment['mime'] ?? null) === 'application/pdf'
                && str_ends_with((string) ($attachment['name'] ?? ''), '.pdf')
        );
    }
}
