<?php

namespace ChurchTools\GoshenPrayerAttendance\Tests\Feature;

require_once dirname(__DIR__).'/bootstrap.php';

use App\Models\Addon;
use App\Models\GoshenAccommodationAllocation;
use App\Models\MobileUser;
use ChurchTools\GoshenPrayerAttendance\Http\Controllers\Api\PrayerAttendanceController;
use ChurchTools\GoshenPrayerAttendance\Models\PrayerAttendanceConfirmation;
use ChurchTools\GoshenPrayerAttendance\Models\PrayerSession;
use ChurchTools\GoshenPrayerAttendance\Http\Controllers\Api\PrayerSessionControlController;
use ChurchTools\GoshenPrayerAttendance\Services\AddonAvailability;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerAttendancePermissionGate;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerAttendanceReportService;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerAttendanceService;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerSessionAttendanceService;
use ChurchTools\GoshenPrayerAttendance\Services\PrayerSessionQrService;
use ChurchTools\GoshenPrayerAttendance\PrayerSessionAttendanceServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\Ticket;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PrayerAttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->register(PrayerSessionAttendanceServiceProvider::class);
        config()->set('prayer-attendance', require dirname(__DIR__, 2).'/config/prayer-attendance.php');

        Artisan::call('migrate', [
            '--path' => realpath(dirname(__DIR__, 2).'/database/migrations'),
            '--realpath' => true,
            '--force' => true,
        ]);
    }

    public function test_self_confirmation_is_ticket_scoped_idempotent_and_qr_is_hashed_at_rest(): void
    {
        [$member, $session, $ticket] = $this->memberSessionAndTicket();
        $attendance = app(PrayerAttendanceService::class);
        $session = $attendance->activate($session, $member);
        $token = $attendance->qrToken($session);

        $first = $attendance->confirmSelf($token, $member, $ticket->public_id, 'device-event-1');
        $retry = $attendance->confirmSelf($token, $member, $ticket->public_id, 'device-event-1');

        $this->assertSame($first->getKey(), $retry->getKey());
        $this->assertSame(1, PrayerAttendanceConfirmation::query()->count());
        $this->assertSame(PrayerAttendanceConfirmation::METHOD_SELF_QR, $first->method);
        $this->assertNotSame($token, $session->qr_token_hash);
        $this->assertSame(hash('sha256', $token), $session->qr_token_hash);
    }

    public function test_closing_session_invalidates_its_qr_and_reopen_requires_a_reason(): void
    {
        [$member, $session] = $this->memberSessionAndTicket();
        $attendance = app(PrayerAttendanceService::class);
        $session = $attendance->activate($session, $member);
        $token = $attendance->qrToken($session);
        $closed = $attendance->close($session, $member);

        $this->assertSame(PrayerSession::STATUS_CLOSED, $closed->status);
        $this->assertNull($closed->qr_token_hash);
        $this->expectException(HttpException::class);
        $attendance->sessionForQrToken($token);
    }

    public function test_reactivating_a_reopened_session_preserves_notification_markers_and_does_not_dispatch_activation_again(): void
    {
        [$member, $session] = $this->memberSessionAndTicket();
        $attendance = app(PrayerAttendanceService::class);
        $session = $attendance->activate($session, $member);
        $firstToken = $attendance->qrToken($session);
        $activatedAt = $session->activated_at;
        $session->forceFill([
            'activation_notification_dispatched_at' => now()->subHour(),
            'reminder_dispatched_at' => now()->subMinutes(30),
        ])->save();

        Queue::fake();
        $attendance->close($session, $member);
        $reopened = $attendance->reopen($session, $member, 'Correcting a premature close.');
        $reactivated = $attendance->activate($reopened, $member);

        $this->assertSame($activatedAt?->toIso8601String(), $reactivated->activated_at?->toIso8601String());
        $this->assertNotNull($reactivated->activation_notification_dispatched_at);
        $this->assertNotNull($reactivated->reminder_dispatched_at);
        $this->assertNotSame($firstToken, $attendance->qrToken($reactivated));
        Queue::assertNothingPushed();
    }

    public function test_qr_services_generate_a_portable_svg_for_admin_and_mobile_delivery_without_gd(): void
    {
        [$member, $session] = $this->memberSessionAndTicket();
        $session = app(PrayerAttendanceService::class)->activate($session, $member);

        $svg = app(PrayerSessionQrService::class)->renderSvg($session);
        $adminResponse = app(PrayerSessionAttendanceService::class)->adminQrResponse($session, true, $member);
        Permission::findOrCreate('prayer_session_attendance.coordinate', 'mobile');
        $member->givePermissionTo('prayer_session_attendance.coordinate');
        $mobileRequest = Request::create('/api/v1/prayer-session-attendance/sessions/'.$session->public_id.'/qr?download=1', 'GET');
        $mobileRequest->setUserResolver(fn () => $member);
        $mobileResponse = app(PrayerAttendanceController::class)->mobileQr(
            $mobileRequest,
            $session->public_id,
            app(PrayerAttendancePermissionGate::class),
            app(PrayerSessionQrService::class),
        );

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('image/svg+xml', (string) $adminResponse->headers->get('Content-Type'));
        $this->assertStringContainsString('.svg', (string) $adminResponse->headers->get('Content-Disposition'));
        $this->assertStringContainsString('image/svg+xml', (string) $mobileResponse->headers->get('Content-Type'));
        $this->assertStringContainsString('.svg', (string) $mobileResponse->headers->get('Content-Disposition'));
    }

    public function test_purchaser_cannot_self_confirm_a_dependants_ticket_without_explicit_ticket_delegation(): void
    {
        [$member, $session, $ownedTicket] = $this->memberSessionAndTicket();
        $attendance = app(PrayerAttendanceService::class);
        $session = $attendance->activate($session, $member);
        $dependentTicket = $this->secondTicketFor($ownedTicket);
        $token = $attendance->qrToken($session);

        $this->assertSame([$ownedTicket->id], collect($attendance->selfEligibleTickets($session, $member))->pluck('id')->all());

        try {
            $attendance->confirmSelf($token, $member, $dependentTicket->public_id);
            $this->fail('A booking purchaser must not self-confirm a dependant ticket without explicit delegation.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->assertSame(0, PrayerAttendanceConfirmation::query()->count());
        }

        $dependentTicket->forceFill([
            'metadata' => ['prayer_attendance' => ['self_confirmation_delegation' => [
                'mobile_user_id' => (string) $member->getKey(),
                'expires_at' => 'not-a-date',
            ]]],
        ])->save();
        $this->assertSame([$ownedTicket->id], collect($attendance->selfEligibleTickets($session, $member))->pluck('id')->all());

        $dependentTicket->forceFill([
            'metadata' => [
                'prayer_attendance' => [
                    'self_confirmation_delegation' => [
                        'mobile_user_id' => (string) $member->getKey(),
                        'expires_at' => now()->addHour()->toIso8601String(),
                    ],
                ],
            ],
        ])->save();

        $confirmed = $attendance->confirmSelf($token, $member, $dependentTicket->public_id);

        $this->assertSame($dependentTicket->id, $confirmed->ticket_id);
    }

    public function test_module_availability_fails_closed_and_explicit_permissions_are_required(): void
    {
        $this->activateAddon();
        $member = $this->member('staff@example.test');
        $addon = Addon::query()->where('package_key', 'prayer_session_attendance')->firstOrFail();
        $addon->update(['status' => Addon::STATUS_INACTIVE]);
        $this->assertFalse(app(AddonAvailability::class)->isActive());

        $addon->update(['status' => Addon::STATUS_ACTIVE]);
        $this->assertFalse(app(PrayerAttendancePermissionGate::class)->allows($member, 'confirm'));
        Permission::findOrCreate('prayer_session_attendance.confirm', 'mobile');
        $member->givePermissionTo('prayer_session_attendance.confirm');
        $this->assertTrue(app(PrayerAttendancePermissionGate::class)->allows($member, 'confirm'));

        $administrator = $this->member('administrator@example.test');
        Permission::findOrCreate('prayer_session_attendance.admin', 'mobile');
        $administrator->givePermissionTo('prayer_session_attendance.admin');
        $this->assertTrue(app(PrayerAttendancePermissionGate::class)->allows($administrator, 'coordinate'));
        $this->assertTrue(app(PrayerAttendancePermissionGate::class)->allows($administrator, 'report'));
    }

    public function test_activation_migration_seeds_the_declared_permissions_for_assignment_on_both_guards(): void
    {
        $declared = collect(config('prayer-attendance.permissions'))->sort()->values()->all();

        foreach (['web', 'mobile'] as $guard) {
            $this->assertSame(
                $declared,
                Permission::query()->where('guard_name', $guard)->whereIn('name', $declared)->orderBy('name')->pluck('name')->sort()->values()->all(),
            );
        }

        $coordinator = $this->member('coordinator@example.test');
        $coordinator->givePermissionTo('prayer_session_attendance.coordinate');

        $this->assertTrue(app(PrayerAttendancePermissionGate::class)->allows($coordinator, 'coordinate'));
        $this->assertFalse(app(PrayerAttendancePermissionGate::class)->allows($coordinator, 'report'));
    }

    public function test_report_rows_include_confirmed_and_not_confirmed_members_with_filterable_history(): void
    {
        [$member, $session, $ticket] = $this->memberSessionAndTicket();
        $member->update(['gender' => 'Female']);
        $ticket->attendee->update(['custom_fields' => ['age_group' => 'Adult']]);
        GoshenAccommodationAllocation::query()->create([
            'event_id' => $ticket->event_id,
            'attendee_id' => $ticket->attendee_id,
            'ticket_id' => $ticket->id,
            'status' => 'assigned',
            'building' => 'Faith House',
            'room' => '12',
            'bed' => 'B',
        ]);

        $secondTicket = $this->secondTicketFor($ticket);
        $secondTicket->attendee->update(['custom_fields' => ['gender' => 'Male', 'age_group' => 'Youth']]);
        $attendance = app(PrayerAttendanceService::class);
        $session = $attendance->activate($session, $member);
        $attendance->confirmStaff($session, $ticket->public_id, $member, PrayerAttendanceConfirmation::METHOD_STAFF_LOOKUP, 'report-first');

        $earlierSession = PrayerSession::query()->create([
            'event_id' => $session->event_id,
            'name' => 'Earlier prayer',
            'status' => PrayerSession::STATUS_SCHEDULED,
        ]);
        $earlierSession = $attendance->activate($earlierSession, $member);
        $attendance->confirmStaff($earlierSession, $ticket->public_id, $member, PrayerAttendanceConfirmation::METHOD_STAFF_SCAN, 'report-history');

        $reports = app(PrayerAttendanceReportService::class);
        $allRows = $reports->rows($session, $reports->filters([]));

        $this->assertCount(2, $allRows);
        $confirmed = collect($allRows)->firstWhere('ticket_id', $ticket->ticket_number);
        $notConfirmed = collect($allRows)->firstWhere('ticket_id', $secondTicket->ticket_number);
        $this->assertSame('Confirmed', $confirmed['status']);
        $this->assertSame('Female', $confirmed['gender']);
        $this->assertSame('Adult', $confirmed['age_group']);
        $this->assertSame('Faith House / 12 / B', $confirmed['residence']);
        $this->assertSame(2, $confirmed['confirmed_sessions']);
        $this->assertStringContainsString('Repeated confirmation', $confirmed['attendance_pattern']);
        $this->assertStringContainsString('Earlier prayer', $confirmed['attendance_history']);
        $this->assertSame('Not Confirmed', $notConfirmed['status']);
        $this->assertSame('Unassigned', $notConfirmed['residence']);

        $filtered = $reports->rows($session, $reports->filters([
            'status' => 'confirmed',
            'gender' => 'female',
            'age_group' => 'Adult',
            'residence' => 'Faith House / 12 / B',
            'repeated' => 'yes',
        ]));
        $this->assertSame([$confirmed], $filtered);
    }

    public function test_report_and_csv_export_apply_the_same_filters(): void
    {
        [$member, $session, $ticket] = $this->memberSessionAndTicket();
        $member->update(['gender' => 'Female']);
        $member->givePermissionTo('prayer_session_attendance.report');
        $ticket->attendee->update(['custom_fields' => ['age_group' => 'Adult']]);
        GoshenAccommodationAllocation::query()->create([
            'event_id' => $ticket->event_id,
            'attendee_id' => $ticket->attendee_id,
            'ticket_id' => $ticket->id,
            'status' => 'assigned',
            'building' => 'Faith House',
        ]);

        $request = Request::create('/api/v1/prayer-session-attendance/control/sessions/'.$session->public_id.'/report', 'GET', [
            'status' => 'not_confirmed',
            'gender' => 'female',
            'age_group' => 'Adult',
            'residence' => 'Faith House',
            'repeated' => 'no',
        ]);
        $request->setUserResolver(fn () => $member);
        $controller = app(PrayerSessionControlController::class);
        $permissions = app(PrayerAttendancePermissionGate::class);
        $attendance = app(PrayerAttendanceService::class);
        $reports = app(PrayerAttendanceReportService::class);

        $report = $controller->report($request, $session->public_id, $permissions, $attendance, $reports)->getData(true);
        $this->assertCount(1, $report['data']['rows']);
        $this->assertSame($ticket->ticket_number, $report['data']['rows'][0]['ticket_id']);
        $this->assertSame(0, $report['data']['filtered_metrics']['confirmed']);
        $this->assertSame(1, $report['data']['filtered_metrics']['not_confirmed']);
        $this->assertSame(1, $report['data']['filtered_metrics']['total']);
        $this->assertEquals(0.0, $report['data']['filtered_metrics']['confirmation_rate']);

        $csvResponse = $controller->export($request, $session->public_id, $permissions, $reports);
        ob_start();
        $csvResponse->sendContent();
        $csv = (string) ob_get_clean();
        $lines = array_values(array_filter(preg_split('/\r\n|\r|\n/', trim($csv))));

        $this->assertCount(2, $lines);
        $this->assertSame('Ticket', str_getcsv($lines[0])[1]);
        $this->assertSame($ticket->ticket_number, str_getcsv($lines[1])[1]);
    }

    public function test_reusing_an_offline_idempotency_key_for_a_different_ticket_is_rejected(): void
    {
        [$member, $session, $ticket] = $this->memberSessionAndTicket();
        $attendance = app(PrayerAttendanceService::class);
        $session = $attendance->activate($session, $member);
        $secondTicket = $this->secondTicketFor($ticket);

        $attendance->confirmStaff($session, $ticket->public_id, $member, PrayerAttendanceConfirmation::METHOD_STAFF_SCAN, 'offline-record-1');

        try {
            $attendance->confirmStaff($session, $secondTicket->public_id, $member, PrayerAttendanceConfirmation::METHOD_STAFF_SCAN, 'offline-record-1');
            $this->fail('A key reused for a different ticket must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    /** @return array{0: MobileUser, 1: PrayerSession, 2: Ticket} */
    private function memberSessionAndTicket(): array
    {
        $this->activateAddon();
        $member = $this->member('attendee@example.test');
        $event = Event::query()->create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-retreat-prayer-tests',
            'type' => EventType::Sequential,
            'timezone' => 'Europe/London',
            'status' => 'published',
            'settings' => ['module' => 'goshen_retreat'],
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
            'customer_id' => $member->id,
            'customer_name' => $member->name,
            'customer_email' => $member->email,
            'currency' => 'GBP',
            'total' => 300,
            'paid_total' => 300,
            'status' => 'paid',
        ]);
        $attendee = Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => 'Prayer',
            'last_name' => 'Attendee',
            'email' => $member->email,
        ]);
        $ticket = Ticket::query()->create([
            'event_id' => $event->id,
            'booking_id' => $booking->id,
            'attendee_id' => $attendee->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => 'PRAYER-TICKET-1',
            'qr_hash' => 'prayer-test-ticket-hash',
            'status' => 'not_checked_in',
        ]);
        $session = PrayerSession::query()->create([
            'event_id' => $event->id,
            'name' => 'Morning Prayer',
            'status' => PrayerSession::STATUS_SCHEDULED,
        ]);

        return [$member, $session, $ticket];
    }

    private function activateAddon(): void
    {
        Addon::query()->updateOrCreate(
            ['package_key' => 'prayer_session_attendance'],
            [
                'name' => 'Prayer Session Attendance',
                'status' => Addon::STATUS_ACTIVE,
                'manifest' => ['capabilities' => []],
            ],
        );
    }

    private function secondTicketFor(Ticket $ticket): Ticket
    {
        $attendee = Attendee::query()->create([
            'booking_id' => $ticket->booking_id,
            'ticket_type_id' => $ticket->ticket_type_id,
            'first_name' => 'Second',
            'last_name' => 'Attendee',
            'email' => 'second-attendee@example.test',
        ]);

        return Ticket::query()->create([
            'event_id' => $ticket->event_id,
            'booking_id' => $ticket->booking_id,
            'attendee_id' => $attendee->id,
            'ticket_type_id' => $ticket->ticket_type_id,
            'ticket_number' => 'PRAYER-TICKET-2',
            'qr_hash' => 'prayer-test-ticket-hash-2',
            'status' => 'not_checked_in',
        ]);
    }

    private function member(string $email): MobileUser
    {
        return MobileUser::query()->create([
            'name' => 'Prayer Member',
            'email' => $email,
            'phone' => '+447700900123',
            'password' => 'secret',
            'login_type' => 'email',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }
}
