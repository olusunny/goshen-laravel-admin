<?php

namespace Tests\Feature;

use App\Filament\Resources\GoshenTicketResource;
use App\Services\DynamicSmtpMailer;
use App\Services\GoshenTicketPdfTemplateSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Mail\TicketIssuedMail;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\Ticket;
use Personal\EventInstallments\Models\TicketEmailLog;
use Personal\EventInstallments\Services\TicketDocumentService;
use Personal\EventInstallments\Services\TicketNotificationService;
use Mockery\MockInterface;
use Tests\TestCase;

class GoshenTicketEmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_notification_service_sends_ticket_with_pdf_attachment(): void
    {
        $this->configureTicketEmailTest();
        $ticket = $this->ticketFixture();

        $log = app(TicketNotificationService::class)->sendTicket($ticket);

        $this->assertSame('sent', $log->status);
        $this->assertSame('ada@example.test', $log->recipient);
        $this->assertNotNull($log->sent_at);
        $this->assertTrue($this->logHasPdfAttachment($log));

        $pdfAttachment = collect($log->attachments)->firstWhere('mime', 'application/pdf');
        $this->assertIsArray($pdfAttachment);
        Storage::disk($pdfAttachment['disk'])->assertExists($pdfAttachment['path']);

        Mail::assertSent(TicketIssuedMail::class, function (TicketIssuedMail $mail): bool {
            return collect($mail->documentAttachments)->contains(
                fn (array $attachment): bool => ($attachment['mime'] ?? null) === 'application/pdf'
                    && str_ends_with((string) ($attachment['name'] ?? ''), '.pdf')
            );
        });
    }

    public function test_admin_send_ticket_email_helper_resends_ticket_with_pdf_attachment(): void
    {
        $this->configureTicketEmailTest();
        $ticket = $this->ticketFixture('resend');

        GoshenTicketResource::sendTicketEmail(
            $ticket,
            'pastoral-office@example.test',
            app(TicketNotificationService::class),
        );

        $log = TicketEmailLog::query()->where('ticket_id', $ticket->id)->latest('id')->firstOrFail();

        $this->assertSame('sent', $log->status);
        $this->assertSame('pastoral-office@example.test', $log->recipient);
        $this->assertTrue($this->logHasPdfAttachment($log));

        Mail::assertSent(TicketIssuedMail::class, function (TicketIssuedMail $mail): bool {
            return collect($mail->documentAttachments)->contains(
                fn (array $attachment): bool => ($attachment['mime'] ?? null) === 'application/pdf'
                    && str_ends_with((string) ($attachment['name'] ?? ''), '.pdf')
            );
        });
    }

    public function test_all_goshen_ticket_pdf_templates_can_generate_a_ticket_pdf(): void
    {
        $this->configureTicketEmailTest(mockMailer: false);
        $ticket = $this->ticketFixture('templates');
        $settings = app(GoshenTicketPdfTemplateSettings::class);
        $documents = app(TicketDocumentService::class);

        foreach (array_keys($settings->templates()) as $template) {
            $settings->save($template);

            $document = $documents->generatePdf($ticket);

            $this->assertSame('pdf', $document->type);
            $this->assertSame('application/pdf', $document->mime_type);
            Storage::disk($document->disk)->assertExists($document->path);
            $this->assertStringStartsWith('%PDF', Storage::disk($document->disk)->get($document->path));
        }
    }

    private function configureTicketEmailTest(bool $mockMailer = true): void
    {
        Storage::fake('local');

        if ($mockMailer) {
            $this->mockTicketMailer();
        }

        Config::set('event-installments.storage.disk', 'local');
        Config::set('event-installments.ticket.qr_secret', 'ticket-email-test-secret');
        Config::set('event-installments.ticket.email.attach_pdf', true);
        Config::set('event-installments.ticket.email.attach_ics', false);
    }

    private function ticketFixture(string $suffix = 'primary'): Ticket
    {
        $event = Event::query()->create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-retreat-2026-'.$suffix,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
        ]);

        $ticketType = EventTicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Adult',
            'currency' => 'GBP',
            'price' => 300,
            'is_active' => true,
        ]);

        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_name' => 'Ada Lovelace',
            'customer_email' => 'booking-'.$suffix.'@example.test',
            'currency' => 'GBP',
            'subtotal' => 300,
            'total' => 300,
            'paid_total' => 300,
            'status' => BookingStatus::Paid,
        ]);

        $attendee = Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.test',
            'phone' => '+447700900123',
        ]);

        return Ticket::query()->create([
            'event_id' => $event->id,
            'booking_id' => $booking->id,
            'attendee_id' => $attendee->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => '0001-'.$suffix,
            'formatted_number' => 'GOS-'.$suffix,
            'qr_hash' => hash('sha256', 'ticket-'.$suffix),
            'status' => TicketStatus::NotCheckedIn,
            'issued_at' => now(),
        ]);
    }

    private function logHasPdfAttachment(TicketEmailLog $log): bool
    {
        return collect($log->attachments)->contains(
            fn (array $attachment): bool => ($attachment['mime'] ?? null) === 'application/pdf'
                && str_ends_with((string) ($attachment['name'] ?? ''), '.pdf')
        );
    }

    private function mockTicketMailer(): void
    {
        Mail::fake();

        $this->mock(DynamicSmtpMailer::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMailable')
                ->once()
                ->andReturnUsing(function (string $to, TicketIssuedMail $mail): void {
                    Mail::to($to)->send($mail);
                });
        });
    }
}
