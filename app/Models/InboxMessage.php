<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboxMessage extends Model
{
    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_CONTROL_HUB = 'control_hub';
    public const SOURCE_AUTOMATIC = 'automatic';
    public const SOURCE_SYSTEM = 'system';
    public const SOURCE_RECURRING_DELIVERY = 'recurring_delivery';

    public const MANAGED_SOURCES = [
        self::SOURCE_ADMIN,
        self::SOURCE_CONTROL_HUB,
    ];

    protected $guarded = [];

    protected $casts = [
        'send_push' => 'boolean',
        'send_inbox' => 'boolean',
        'send_email' => 'boolean',
        'is_published' => 'boolean',
        'schedule_enabled' => 'boolean',
        'notification_tone_enabled' => 'boolean',
        'selected_mobile_user_ids' => 'array',
        'selected_church_group_ids' => 'array',
        'selected_country_of_residences' => 'array',
        'selected_states_counties_provinces' => 'array',
        'selected_genders' => 'array',
        'selected_role_ids' => 'array',
        'delivered_mobile_user_ids' => 'array',
        'published_at' => 'datetime',
        'push_sent_at' => 'datetime',
        'email_sent_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'next_dispatch_at' => 'datetime',
        'last_dispatched_at' => 'datetime',
        'goshen_event_id' => 'integer',
        'goshen_paid_from' => 'datetime',
        'goshen_paid_until' => 'datetime',
        'goshen_recent_days' => 'integer',
        'goshen_paid_week' => 'date',
        'fundraising_campaign_id' => 'integer',
        'goshen_quiz_id' => 'integer',
    ];

    public static function sourceOptions(): array
    {
        return [
            self::SOURCE_ADMIN => 'Admin-created messages',
            self::SOURCE_CONTROL_HUB => 'Flutter control hub messages',
            self::SOURCE_AUTOMATIC => 'Automatic messages',
            self::SOURCE_SYSTEM => 'System event messages',
            self::SOURCE_RECURRING_DELIVERY => 'Recurring delivery copies',
        ];
    }
}
