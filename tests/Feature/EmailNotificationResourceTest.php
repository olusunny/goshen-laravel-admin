<?php

namespace Tests\Feature;

use App\Models\EmailNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailNotificationResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_page_cancel_uses_the_notification_list_even_when_the_referrer_is_the_service_worker(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@church.local')->firstOrFail();
        $notification = EmailNotification::query()->create([
            'subject' => 'Accommodation update',
            'body' => 'Your room details are ready.',
        ]);

        $this->actingAs($admin)
            ->from('/member-sw.js')
            ->get("/admin/email-notifications/{$notification->id}/edit")
            ->assertOk()
            ->assertSee('x-on:click="window.location.href =', false)
            ->assertSee('/admin/email-notifications', false)
            ->assertSee('{user: accommodation_building}', false)
            ->assertSee('{user: accommodation}', false);
    }
}
