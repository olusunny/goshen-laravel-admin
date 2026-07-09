<?php

namespace Tests\Feature;

use App\Models\InboxMessage;
use App\Models\GoshenQuiz;
use App\Models\GoshenQuizAttempt;
use App\Models\MobileUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Personal\EventInstallments\Enums\BookingStatus;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Enums\InstallmentStatus;
use Personal\EventInstallments\Models\Booking;
use Personal\EventInstallments\Models\Event;
use Personal\EventInstallments\Models\PaymentInstallment;
use Personal\EventInstallments\Models\PaymentTransaction;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Sunny\Fundraising\Models\Campaign;
use Sunny\Fundraising\Models\CampaignContribution;
use Tests\TestCase;

class ControlHubMessagingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_authorized_manager_can_send_control_hub_message(): void
    {
        $regular = $this->member('message-regular@example.test', 'Message Regular');
        $regularToken = $regular->issueApiToken();

        $this->withHeader('Authorization', 'Bearer '.$regularToken)
            ->postJson('/api/control-hub/messages/send', [
                'data' => $this->messagePayload($regularToken),
            ])
            ->assertForbidden();

        $manager = $this->manager();
        $managerToken = $manager->issueApiToken();

        $this->withHeader('Authorization', 'Bearer '.$managerToken)
            ->postJson('/api/control-hub/messages/send', [
                'data' => $this->messagePayload($managerToken),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.title', 'Goshen update')
            ->assertJsonPath('data.send_push', false);

        $message = InboxMessage::query()->firstOrFail();
        $this->assertSame('Goshen update', $message->title);
        $this->assertSame('events', $message->notification_category);
        $this->assertSame('all', $message->recipient_mode);
        $this->assertFalse((bool) $message->send_push);
        $this->assertTrue((bool) $message->is_published);
    }

    public function test_control_hub_message_targets_paid_goshen_edition_members(): void
    {
        $manager = $this->manager();
        $token = $manager->issueApiToken();
        $event = $this->goshenEvent();
        $paid = $this->member('paid-goshen@example.test', 'Paid Goshen');
        $unpaid = $this->member('unpaid-goshen@example.test', 'Unpaid Goshen');

        $this->booking($event, $paid, true, now()->subDays(2));
        $this->booking($event, $unpaid, false);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/control-hub/messages/send', [
                'data' => array_merge($this->messagePayload($token), [
                    'recipient_mode' => 'goshen_paid',
                    'goshen_event_id' => $event->id,
                    'send_inbox' => true,
                    'send_push' => false,
                ]),
            ])
            ->assertOk()
            ->assertJsonPath('data.recipient_mode', 'goshen_paid');

        $message = InboxMessage::query()->firstOrFail();

        $this->assertContains($paid->id, $message->delivered_mobile_user_ids);
        $this->assertNotContains($unpaid->id, $message->delivered_mobile_user_ids);
    }

    public function test_control_hub_message_targets_goshen_paid_date_range(): void
    {
        $manager = $this->manager();
        $token = $manager->issueApiToken();
        $event = $this->goshenEvent();
        $inside = $this->member('inside-range@example.test', 'Inside Range');
        $outside = $this->member('outside-range@example.test', 'Outside Range');

        $this->booking($event, $inside, true, now()->subDays(3));
        $this->booking($event, $outside, true, now()->subMonth());

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/control-hub/messages/send', [
                'data' => array_merge($this->messagePayload($token), [
                    'recipient_mode' => 'goshen_paid_between',
                    'goshen_event_id' => $event->id,
                    'goshen_paid_from' => now()->subWeek()->toIso8601String(),
                    'goshen_paid_until' => now()->toIso8601String(),
                    'send_inbox' => true,
                    'send_push' => false,
                ]),
            ])
            ->assertOk();

        $message = InboxMessage::query()->firstOrFail();

        $this->assertContains($inside->id, $message->delivered_mobile_user_ids);
        $this->assertNotContains($outside->id, $message->delivered_mobile_user_ids);
    }

    public function test_control_hub_message_targets_fundraising_and_quiz_participants(): void
    {
        $manager = $this->manager();
        $token = $manager->issueApiToken();
        $fundraisingMember = $this->member('fundraising-participant@example.test', 'Fundraising Participant');
        $quizMember = $this->member('quiz-participant@example.test', 'Quiz Participant');
        $bystander = $this->member('message-bystander@example.test', 'Bystander');
        $campaign = Campaign::query()->create([
            'title' => 'Mission chairs',
            'slug' => 'mission-chairs',
            'cause' => 'Church seating',
            'goal_amount' => 10000,
            'raised_amount' => 100,
            'currency' => 'GBP',
            'status' => Campaign::STATUS_ACTIVE,
            'end_at' => now()->addMonth(),
        ]);
        CampaignContribution::query()->create([
            'campaign_id' => $campaign->id,
            'user_id' => $fundraisingMember->id,
            'user_type' => MobileUser::class,
            'amount' => 100,
            'currency' => 'GBP',
            'status' => CampaignContribution::STATUS_SUCCEEDED,
            'succeeded_at' => now(),
        ]);
        $quiz = GoshenQuiz::query()->create([
            'title' => 'Retreat quiz',
            'is_active' => true,
        ]);
        GoshenQuizAttempt::query()->create([
            'quiz_id' => $quiz->id,
            'mobile_user_id' => $quizMember->id,
            'status' => GoshenQuizAttempt::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/control-hub/messages/send', [
                'data' => array_merge($this->messagePayload($token), [
                    'recipient_mode' => 'fundraising_participants',
                    'fundraising_campaign_id' => $campaign->id,
                    'send_inbox' => true,
                    'send_push' => false,
                ]),
            ])
            ->assertOk();

        $fundraisingMessage = InboxMessage::query()->latest('id')->firstOrFail();
        $this->assertContains($fundraisingMember->id, $fundraisingMessage->delivered_mobile_user_ids);
        $this->assertNotContains($bystander->id, $fundraisingMessage->delivered_mobile_user_ids);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/control-hub/messages/send', [
                'data' => array_merge($this->messagePayload($token), [
                    'recipient_mode' => 'quiz_participants',
                    'goshen_quiz_id' => $quiz->id,
                    'send_inbox' => true,
                    'send_push' => false,
                ]),
            ])
            ->assertOk();

        $quizMessage = InboxMessage::query()->latest('id')->firstOrFail();
        $this->assertContains($quizMember->id, $quizMessage->delivered_mobile_user_ids);
        $this->assertNotContains($bystander->id, $quizMessage->delivered_mobile_user_ids);
    }

    public function test_scheduled_control_hub_message_stays_unpublished_until_due(): void
    {
        $manager = $this->manager();
        $token = $manager->issueApiToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/control-hub/messages/send', [
                'data' => array_merge($this->messagePayload($token), [
                    'send_inbox' => true,
                    'send_push' => false,
                    'scheduled_at' => now()->addHour()->toIso8601String(),
                ]),
            ])
            ->assertOk()
            ->assertJsonPath('data.scheduled', true);

        $message = InboxMessage::query()->firstOrFail();

        $this->assertFalse((bool) $message->is_published);
        $this->assertTrue((bool) $message->schedule_enabled);
        $this->assertNotNull($message->next_dispatch_at);
    }

    private function member(string $email, string $name): MobileUser
    {
        return MobileUser::query()->create([
            'name' => $name,
            'email' => $email,
            'phone' => '+447700901'.random_int(100, 999),
            'password' => 'secret',
            'login_type' => 'email',
            'member_type' => 'church_member',
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function manager(): MobileUser
    {
        Permission::findOrCreate('send_admin_messages', 'mobile');
        $role = Role::findOrCreate('event_manager', 'mobile');
        $role->givePermissionTo('send_admin_messages');

        $manager = $this->member('message-manager@example.test', 'Message Manager');
        $manager->assignRole($role);

        return $manager;
    }

    private function messagePayload(string $token): array
    {
        return [
            'api_token' => $token,
            'title' => 'Goshen update',
            'content' => 'Registration desk opens at 9am.',
            'notification_category' => 'events',
            'recipient_mode' => 'all',
            'send_push' => false,
        ];
    }

    private function goshenEvent(): Event
    {
        return Event::query()->create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-retreat-'.str()->random(8),
            'type' => EventType::Sequential,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
            'settings' => ['module' => 'goshen_retreat'],
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addMonth(),
        ]);
    }

    private function booking(Event $event, MobileUser $member, bool $paid, ?\DateTimeInterface $paidAt = null): Booking
    {
        $booking = Booking::query()->create([
            'event_id' => $event->id,
            'customer_id' => $member->id,
            'customer_name' => $member->name,
            'customer_email' => $member->email,
            'customer_phone' => $member->phone,
            'currency' => 'GBP',
            'subtotal' => 100,
            'total' => 100,
            'paid_total' => $paid ? 100 : 0,
            'status' => $paid ? BookingStatus::Paid : BookingStatus::Pending,
        ]);

        $installment = PaymentInstallment::query()->create([
            'booking_id' => $booking->id,
            'sequence' => 1,
            'currency' => 'GBP',
            'amount' => 100,
            'paid_amount' => $paid ? 100 : 0,
            'due_on' => now()->addWeek()->toDateString(),
            'paid_at' => $paid ? ($paidAt ?: now()) : null,
            'status' => $paid ? InstallmentStatus::Paid : InstallmentStatus::Pending,
        ]);

        if ($paid) {
            PaymentTransaction::query()->create([
                'booking_id' => $booking->id,
                'installment_id' => $installment->id,
                'gateway' => 'stripe',
                'provider_reference' => 'test_'.str()->ulid(),
                'currency' => 'GBP',
                'amount' => 100,
                'status' => 'paid',
                'paid_at' => $paidAt ?: now(),
            ]);
        }

        return $booking->refresh();
    }
}
