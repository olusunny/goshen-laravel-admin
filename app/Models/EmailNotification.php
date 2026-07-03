<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailNotification extends Model
{
    protected $guarded = [];

    protected $casts = [
        'selected_mobile_user_ids' => 'array',
        'selected_church_group_ids' => 'array',
        'selected_country_of_residences' => 'array',
        'selected_states_counties_provinces' => 'array',
        'selected_genders' => 'array',
        'selected_role_ids' => 'array',
        'sent_at' => 'datetime',
        'goshen_event_id' => 'integer',
        'goshen_paid_from' => 'datetime',
        'goshen_paid_until' => 'datetime',
        'goshen_recent_days' => 'integer',
        'goshen_paid_week' => 'date',
        'fundraising_campaign_id' => 'integer',
        'goshen_quiz_id' => 'integer',
    ];
}
