<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\GoshenAccommodationAllocation;
use App\Models\GoshenExperienceQuestion;
use App\Models\GoshenExperienceResponse;
use App\Models\GoshenExperienceSurvey;
use App\Models\MobileUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Enums\TicketStatus;
use Personal\EventInstallments\Models\Attendee;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\BookingLine;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\EventSchedule;
use Personal\EventInstallments\Models\EventTicketType;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentTransaction;
use Personal\EventInstallments\Models\Ticket;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoshenRetreatMemberAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_dashboard_rejects_email_only_identity_payload(): void
    {
        $this->verifiedMember();

        $this->postJson('/api/goshen-retreat/me', [
            'data' => ['email' => 'member@example.test'],
        ])
            ->assertUnauthorized()
            ->assertJsonPath('status', 'error');
    }

    public function test_member_dashboard_accepts_valid_mobile_api_token(): void
    {
        $user = $this->verifiedMember();
        $token = $user->issueApiToken();

        $this->postJson('/api/goshen-retreat/me', [
            'data' => ['api_token' => $token],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.user.email', 'member@example.test');
    }

    public function test_published_retreat_events_include_schedule_payload(): void
    {
        $this->verifiedMember();
        [$event] = $this->publishedRetreatEvent();

        $this->getJson('/api/goshen-retreat/events')
            ->assertOk()
            ->assertJsonPath('data.0.public_id', $event->public_id)
            ->assertJsonPath('data.0.schedules.0.day_number', 1)
            ->assertJsonPath('data.0.schedules.0.title', 'Opening service');
    }

    public function test_event_manager_can_view_registration_management_summary(): void
    {
        $manager = $this->verifiedMember('manager@example.test', 'Goshen Manager', '+2348011113333');
        $member = $this->verifiedMember('retreat-member@example.test', 'Retreat Member', '+2348011114444');
        Role::findOrCreate('event_manager', 'mobile');
        $manager->assignRole('event_manager');

        [$event, $ticketType] = $this->publishedRetreatEvent(price: 150);

        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_id' => $member->id,
            'customer_name' => $member->name,
            'customer_email' => $member->email,
            'customer_phone' => $member->phone,
            'currency' => 'NGN',
            'subtotal' => 300,
            'total' => 300,
            'paid_total' => 300,
            'status' => BookingStatus::Paid,
            'metadata' => [
                'payment_mode' => 'wallet',
                'uk_privacy_consent' => true,
            ],
        ]);

        BookingLine::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 2,
            'currency' => 'NGN',
            'unit_price' => 150,
            'line_total' => 300,
        ]);

        Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => 'Retreat',
            'last_name' => 'Member',
            'email' => $member->email,
            'phone' => $member->phone,
            'company' => 'Goshen Farms Ltd',
            'designation' => 'Media Coordinator',
            'custom_fields' => [
                'title' => 'Mr.',
                'gender' => 'male',
                'marital_status' => 'Married',
                'age_group' => 'adult',
                'free_church_bus_interest' => 'yes',
                'volunteer_department' => 'media',
            ],
        ]);

        Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => 'Second',
            'last_name' => 'Attendee',
            'email' => 'second@example.test',
            'phone' => '+2348011115555',
            'company' => 'Mercy Care',
            'designation' => 'Children Lead',
            'custom_fields' => [
                'title' => 'Mrs.',
                'gender' => 'female',
                'marital_status' => 'Married',
                'age_group' => 'young_adult',
                'free_church_bus_interest' => 'no_thanks',
                'volunteer_department' => 'children_department',
            ],
        ]);

        PaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'gateway' => 'wallet',
            'provider_reference' => 'wallet_test_reference',
            'currency' => 'NGN',
            'amount' => 300,
            'status' => 'paid',
            'paid_at' => now(),
            'payload' => ['source' => 'goshen_wallet'],
        ]);

        Booking::query()->create([
            'event_id' => $event->id,
            'customer_id' => $member->id,
            'customer_name' => 'Cancelled Member',
            'customer_email' => 'cancelled@example.test',
            'customer_phone' => '+2348011116666',
            'currency' => 'NGN',
            'subtotal' => 900,
            'total' => 900,
            'paid_total' => 0,
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Changed plans',
        ]);

        $this->postJson("/api/goshen-retreat/events/{$event->public_id}/management-summary", [
            'data' => ['api_token' => $member->issueApiToken()],
        ])
            ->assertForbidden()
            ->assertJsonPath('status', 'error');

        $managerToken = $manager->issueApiToken();
        $response = $this->postJson("/api/goshen-retreat/events/{$event->public_id}/management-summary", [
            'data' => ['api_token' => $managerToken],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.totals.registrations', 2)
            ->assertJsonPath('data.totals.attendees', 2)
            ->assertJsonPath('data.totals.cancelled_registrations', 1)
            ->assertJsonPath('data.totals.total_amount', 300)
            ->assertJsonPath('data.totals.balance_amount', 0)
            ->assertJsonPath('data.totals.wallet_paid_amount', 300)
            ->assertJsonPath('data.attendees.0.company', 'Goshen Farms Ltd')
            ->assertJsonPath('data.attendees.0.designation', 'Media Coordinator')
            ->assertJsonPath('data.attendees.0.free_church_bus_interest_label', 'Yes');

        $gender = collect($response->json('data.breakdowns.gender'))->pluck('count', 'key');
        $bus = collect($response->json('data.breakdowns.free_church_bus_interest'))->pluck('count', 'key');
        $volunteer = collect($response->json('data.breakdowns.volunteer_department'))->pluck('count', 'key');
        $ticketTypes = collect($response->json('data.breakdowns.ticket_type'))->pluck('count', 'key');
        $company = collect($response->json('data.breakdowns.company'))->pluck('count', 'key');
        $designation = collect($response->json('data.breakdowns.designation'))->pluck('count', 'key');
        $privacyConsent = collect($response->json('data.breakdowns.privacy_consent'))->pluck('count', 'key');
        $registrations = collect($response->json('data.registrations'));

        $this->assertSame(1, $gender->get('male'));
        $this->assertSame(1, $gender->get('female'));
        $this->assertSame(1, $bus->get('yes'));
        $this->assertSame(1, $volunteer->get('media'));
        $this->assertSame(2, $ticketTypes->get('Adult'));
        $this->assertSame(1, $company->get('Goshen Farms Ltd'));
        $this->assertSame(1, $designation->get('Media Coordinator'));
        $this->assertSame(1, $privacyConsent->get('accepted'));
        $this->assertTrue($registrations->contains(fn (array $row): bool => ($row['payment_mode'] ?? null) === 'wallet'));
        $this->assertTrue($registrations->contains(fn (array $row): bool => ($row['privacy_consent'] ?? null) === 'accepted'));
        $this->assertTrue($registrations->contains(fn (array $row): bool => ($row['status'] ?? null) === BookingStatus::Cancelled->value && (float) ($row['balance'] ?? 1) === 0.0));

        $this->postJson("/api/goshen-retreat/events/{$event->id}/management-summary", [
            'data' => ['api_token' => $managerToken],
        ])
            ->assertOk()
            ->assertJsonPath('data.event.public_id', $event->public_id);

        $this->postJson('/api/goshen-retreat/me', [
            'data' => ['api_token' => $managerToken],
        ])
            ->assertOk()
            ->assertJsonPath('data.user.can_manage_goshen_registration', true);
    }

    public function test_event_manager_can_manage_accommodation_allocations(): void
    {
        $manager = $this->verifiedMember('accommodation-manager@example.test', 'Accommodation Manager', '+2348011116667');
        $member = $this->verifiedMember('accommodation-member@example.test', 'Accommodation Member', '+2348011116668');
        Role::findOrCreate('event_manager', 'mobile');
        $manager->assignRole('event_manager');

        [$event, $ticketType] = $this->publishedRetreatEvent(price: 200);

        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_id' => $member->id,
            'customer_name' => $member->name,
            'customer_email' => $member->email,
            'customer_phone' => $member->phone,
            'currency' => 'GBP',
            'subtotal' => 200,
            'total' => 200,
            'paid_total' => 200,
            'status' => BookingStatus::Paid,
        ]);

        $attendee = Attendee::query()->create([
            'booking_id' => $booking->id,
            'ticket_type_id' => $ticketType->id,
            'first_name' => 'Accommodation',
            'last_name' => 'Member',
            'email' => $member->email,
            'phone' => $member->phone,
            'custom_fields' => ['gender' => 'female', 'age_group' => 'adult'],
        ]);

        $ticket = Ticket::query()->create([
            'event_id' => $event->id,
            'booking_id' => $booking->id,
            'attendee_id' => $attendee->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => '000777',
            'formatted_number' => 'GOSHEN-000777',
            'qr_hash' => 'accommodation-manager-test',
            'status' => TicketStatus::NotCheckedIn,
            'issued_at' => now(),
        ]);

        $this->postJson("/api/goshen-retreat/events/{$event->public_id}/accommodation-management", [
            'data' => ['api_token' => $member->issueApiToken()],
        ])
            ->assertForbidden()
            ->assertJsonPath('status', 'error');

        $managerToken = $manager->issueApiToken();
        $this->postJson("/api/goshen-retreat/events/{$event->public_id}/accommodation-management", [
            'data' => ['api_token' => $managerToken],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.totals.eligible_attendees', 1)
            ->assertJsonPath('data.totals.unallocated', 1)
            ->assertJsonPath('data.eligible_attendees.0.ticket_id', $ticket->id);

        $createResponse = $this->postJson('/api/goshen-retreat/accommodation-allocations', [
            'data' => [
                'api_token' => $managerToken,
                'event_id' => $event->public_id,
                'attendee_id' => $attendee->id,
                'status' => 'assigned',
                'building' => 'Mercy Hall',
                'room' => 'A12',
                'bed' => 'B',
                'check_in_note' => 'Arrive before 6pm',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('allocation.building', 'Mercy Hall')
            ->assertJsonPath('allocation.attendee_id', $attendee->id)
            ->assertJsonPath('allocation.ticket_id', $ticket->id);

        $allocationId = $createResponse->json('allocation.id');
        $this->assertDatabaseHas('goshen_accommodation_allocations', [
            'id' => $allocationId,
            'event_id' => $event->id,
            'attendee_id' => $attendee->id,
            'ticket_id' => $ticket->id,
            'status' => 'assigned',
            'building' => 'Mercy Hall',
        ]);

        $this->postJson("/api/goshen-retreat/events/{$event->id}/accommodation-management", [
            'data' => ['api_token' => $managerToken],
        ])
            ->assertOk()
            ->assertJsonPath('data.totals.allocated', 1)
            ->assertJsonPath('data.totals.assigned', 1)
            ->assertJsonPath('data.eligible_attendees.0.current_allocation.building', 'Mercy Hall');

        $this->postJson("/api/goshen-retreat/accommodation-allocations/{$allocationId}", [
            'data' => [
                'api_token' => $managerToken,
                'status' => 'changed',
                'room' => 'A14',
                'bed' => 'C',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('allocation.status', 'changed')
            ->assertJsonPath('allocation.room', 'A14')
            ->assertJsonPath('allocation.attendee_visible_details.Room', 'A14');

        $this->assertSame(
            'changed',
            GoshenAccommodationAllocation::query()->findOrFail($allocationId)->status,
        );
    }

    public function test_event_manager_can_view_survey_question_stats_with_numeric_event_id(): void
    {
        $manager = $this->verifiedMember('survey-manager@example.test', 'Survey Manager', '+2348011117777');
        $member = $this->verifiedMember('survey-member@example.test', 'Survey Member', '+2348011118888');
        Role::findOrCreate('event_manager', 'mobile');
        $manager->assignRole('event_manager');

        [$event] = $this->publishedRetreatEvent(price: 0);
        $survey = GoshenExperienceSurvey::query()->create([
            'event_id' => $event->id,
            'title' => 'Retreat T-shirt survey',
            'description' => 'Collect retreat feedback.',
            'is_active' => true,
            'allow_audio' => true,
            'allow_video' => true,
            'allow_all_authenticated_users' => true,
        ]);
        $shirtQuestion = GoshenExperienceQuestion::query()->create([
            'survey_id' => $survey->id,
            'prompt' => 'Which T-shirt do you want?',
            'type' => GoshenExperienceQuestion::TYPE_CHOICE,
            'options' => ['White', 'Black'],
            'is_required' => true,
            'sort_order' => 1,
        ]);
        $storyQuestion = GoshenExperienceQuestion::query()->create([
            'survey_id' => $survey->id,
            'prompt' => 'Share your testimony',
            'type' => GoshenExperienceQuestion::TYPE_TEXTAREA,
            'options' => [],
            'is_required' => false,
            'sort_order' => 2,
        ]);

        GoshenExperienceResponse::query()->create([
            'survey_id' => $survey->id,
            'event_id' => $event->id,
            'mobile_user_id' => $member->id,
            'story' => 'The retreat was a blessing.',
            'audio_path' => 'goshen/experience/audio/testimony.wav',
            'audio_duration_seconds' => 17,
            'answers' => [
                (string) $shirtQuestion->id => [
                    'question_id' => $shirtQuestion->id,
                    'prompt' => $shirtQuestion->prompt,
                    'type' => $shirtQuestion->type,
                    'answer' => 'White',
                ],
                (string) $storyQuestion->id => [
                    'question_id' => $storyQuestion->id,
                    'prompt' => $storyQuestion->prompt,
                    'type' => $storyQuestion->type,
                    'answer' => 'I felt refreshed and encouraged.',
                ],
            ],
            'submitted_at' => now(),
        ]);

        $response = $this->postJson("/api/goshen-retreat/experience/events/{$event->id}/stats", [
            'api_token' => $manager->issueApiToken(),
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.event.public_id', $event->public_id)
            ->assertJsonPath('data.surveys.0.title', 'Retreat T-shirt survey')
            ->assertJsonPath('data.question_stats.0.prompt', 'Which T-shirt do you want?')
            ->assertJsonPath('data.question_stats.0.breakdown.0.label', 'White')
            ->assertJsonPath('data.recent_responses.0.member_name', 'Survey Member')
            ->assertJsonPath('data.recent_responses.0.answers.0.answer', 'White')
            ->assertJsonPath('data.recent_responses.0.audio_duration_seconds', 17);

        $this->assertStringContainsString(
            'goshen/experience/audio/testimony.wav',
            (string) $response->json('data.recent_responses.0.audio_url'),
        );
    }

    public function test_event_manager_can_update_survey_management_settings(): void
    {
        $manager = $this->verifiedMember('survey-settings-manager@example.test', 'Survey Manager', '+2348011119999');
        $member = $this->verifiedMember('survey-settings-member@example.test', 'Survey Member', '+2348011120000');
        Role::findOrCreate('event_manager', 'mobile');
        $manager->assignRole('event_manager');

        [$event] = $this->publishedRetreatEvent(price: 0);
        $survey = GoshenExperienceSurvey::query()->create([
            'event_id' => $event->id,
            'title' => 'Retreat media survey',
            'description' => 'Collect retreat media preferences.',
            'is_active' => true,
            'allow_audio' => true,
            'allow_video' => false,
            'allow_all_authenticated_users' => false,
        ]);
        GoshenExperienceQuestion::query()->create([
            'survey_id' => $survey->id,
            'prompt' => 'Can we share your testimony?',
            'type' => GoshenExperienceQuestion::TYPE_CHOICE,
            'options' => ['Yes', 'No'],
            'is_required' => true,
            'sort_order' => 1,
        ]);

        $this->postJson("/api/goshen-retreat/experience/surveys/{$survey->id}/settings", [
            'data' => [
                'api_token' => $member->issueApiToken(),
                'is_active' => false,
            ],
        ])
            ->assertForbidden()
            ->assertJsonPath('status', 'error');

        $managerToken = $manager->issueApiToken();
        $this->postJson("/api/goshen-retreat/experience/surveys/{$survey->id}/settings", [
            'data' => [
                'api_token' => $managerToken,
                'is_active' => false,
                'allow_audio' => false,
                'allow_video' => true,
                'allow_all_authenticated_users' => true,
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('survey.is_active', false)
            ->assertJsonPath('survey.allow_audio', false)
            ->assertJsonPath('survey.allow_video', true)
            ->assertJsonPath('survey.allow_all_authenticated_users', true)
            ->assertJsonPath('survey.questions_count', 1);

        $this->assertDatabaseHas('goshen_experience_surveys', [
            'id' => $survey->id,
            'is_active' => false,
            'allow_audio' => false,
            'allow_video' => true,
            'allow_all_authenticated_users' => true,
        ]);

        $this->postJson("/api/goshen-retreat/experience/events/{$event->public_id}/stats", [
            'data' => ['api_token' => $managerToken],
        ])
            ->assertOk()
            ->assertJsonPath('data.surveys.0.allow_audio', false)
            ->assertJsonPath('data.surveys.0.allow_video', true)
            ->assertJsonPath('data.surveys.0.allow_all_authenticated_users', true);

        $this->postJson('/api/goshen-retreat/experience/surveys/999999/settings', [
            'data' => [
                'api_token' => $managerToken,
                'is_active' => true,
            ],
        ])
            ->assertNotFound()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'The selected Goshen Experience survey could not be found.');
    }

    public function test_booking_creation_rejects_email_only_identity_payload(): void
    {
        $user = $this->verifiedMember();
        [$event, $ticketType] = $this->publishedRetreatEvent();

        $this->postJson('/api/goshen-retreat/bookings', [
            'data' => [
                'email' => $user->email,
                'event_id' => $event->public_id,
                'ticket_type_id' => $ticketType->public_id,
                'quantity' => 1,
                'attendees' => [
                    [
                        'first_name' => 'Member',
                        'last_name' => 'Test',
                        'gender' => 'male',
                        'age_group' => 'adult',
                    ],
                ],
            ],
        ])
            ->assertUnauthorized()
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseMissing('ei_bookings', [
            'customer_email' => $user->email,
        ]);
    }

    public function test_booking_creation_requires_attendee_dropdown_choices(): void
    {
        $user = $this->verifiedMember();
        $token = $user->issueApiToken();
        [$event, $ticketType] = $this->publishedRetreatEvent();

        $this->postJson('/api/goshen-retreat/bookings', [
            'data' => [
                'api_token' => $token,
                'event_id' => $event->public_id,
                'ticket_type_id' => $ticketType->public_id,
                'quantity' => 1,
                'uk_privacy_consent' => true,
                'privacy_policy_version' => 'uk-gdpr-2026-06',
                'attendees' => [
                    [
                        'first_name' => 'Member',
                        'last_name' => 'Test',
                        'gender' => 'male',
                        'age_group' => 'not_specified',
                        'free_church_bus_interest' => 'yes',
                        'volunteer_department' => 'media',
                    ],
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error');

        $this->postJson('/api/goshen-retreat/bookings', [
            'data' => [
                'api_token' => $token,
                'event_id' => $event->public_id,
                'ticket_type_id' => $ticketType->public_id,
                'quantity' => 1,
                'uk_privacy_consent' => true,
                'privacy_policy_version' => 'uk-gdpr-2026-06',
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseMissing('ei_bookings', [
            'customer_id' => $user->id,
        ]);
    }

    public function test_installment_checkout_rejects_email_only_identity_payload(): void
    {
        $user = $this->verifiedMember();
        [$booking, $installment] = $this->bookingWithInstallment($user);

        $this->postJson("/api/goshen-retreat/bookings/{$booking->public_id}/payments/{$installment->public_id}/checkout", [
            'data' => ['email' => $user->email],
        ])
            ->assertUnauthorized()
            ->assertJsonPath('status', 'error');

        $this->assertDatabaseMissing('ei_payment_transactions', [
            'booking_id' => $booking->id,
            'installment_id' => $installment->id,
        ]);
    }

    public function test_free_retreat_registration_is_confirmed_and_issues_one_ticket_per_attendee(): void
    {
        $user = $this->verifiedMember();
        $token = $user->issueApiToken();
        [$event, $ticketType] = $this->publishedRetreatEvent(price: 0);

        $this->postJson('/api/goshen-retreat/bookings', [
            'data' => [
                'api_token' => $token,
                'event_id' => $event->public_id,
                'ticket_type_id' => $ticketType->public_id,
                'quantity' => 2,
                'uk_privacy_consent' => true,
                'privacy_policy_version' => 'uk-gdpr-2026-06',
                'attendees' => [
                    [
                        'title' => 'Mr.',
                        'first_name' => 'Member',
                        'last_name' => 'One',
                        'designation' => 'worker',
                        'gender' => 'male',
                        'marital_status' => 'Married',
                        'age_group' => 'adult',
                        'free_church_bus_interest' => 'yes',
                        'volunteer_department' => 'media',
                    ],
                    [
                        'title' => 'Miss',
                        'first_name' => 'Member',
                        'last_name' => 'Two',
                        'designation' => 'guest',
                        'gender' => 'female',
                        'marital_status' => 'Single',
                        'age_group' => 'adult',
                        'free_church_bus_interest' => 'no_thanks',
                        'volunteer_department' => 'no_chance_at_the_moment',
                    ],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('booking.status', BookingStatus::Paid->value)
            ->assertJsonCount(0, 'booking.installments')
            ->assertJsonCount(2, 'booking.tickets');

        $booking = Booking::query()->where('customer_id', $user->id)->latest()->firstOrFail();

        $this->assertSame(2, Ticket::query()->where('booking_id', $booking->id)->count());
        $this->assertSame(0, PaymentInstallment::query()->where('booking_id', $booking->id)->count());
        $this->assertSame('yes', $booking->metadata['free_church_bus_interest'] ?? null);

        $firstAttendee = Attendee::query()->where('booking_id', $booking->id)->orderBy('id')->firstOrFail();
        $this->assertSame('worker', $firstAttendee->designation);
        $this->assertSame('worker', $firstAttendee->custom_fields['designation'] ?? null);
        $this->assertSame('yes', $firstAttendee->custom_fields['free_church_bus_interest'] ?? null);
        $this->assertSame('media', $firstAttendee->custom_fields['volunteer_department'] ?? null);
    }

    private function verifiedMember(
        string $email = 'member@example.test',
        string $name = 'Member Test',
        string $phone = '+2348011112222',
    ): MobileUser
    {
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
    private function publishedRetreatEvent(float $price = 1000): array
    {
        $event = Event::query()->create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-retreat-2026',
            'type' => EventType::Sequential,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addMonth(),
            'settings' => [],
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
    private function bookingWithInstallment(MobileUser $user): array
    {
        [$event] = $this->publishedRetreatEvent();

        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_id' => $user->id,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $user->phone,
            'currency' => 'NGN',
            'subtotal' => 1000,
            'total' => 1000,
            'paid_total' => 0,
            'status' => BookingStatus::Pending,
        ]);

        $installment = PaymentInstallment::query()->create([
            'booking_id' => $booking->id,
            'sequence' => 1,
            'currency' => 'NGN',
            'amount' => 1000,
            'paid_amount' => 0,
            'due_on' => now()->addWeek()->toDateString(),
            'status' => InstallmentStatus::Pending,
        ]);

        return [$booking->refresh(), $installment->refresh()];
    }
}
