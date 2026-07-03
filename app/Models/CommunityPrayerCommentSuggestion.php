<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityPrayerCommentSuggestion extends Model
{
    protected $guarded = [];

    public function prayerRequest(): BelongsTo
    {
        return $this->belongsTo(CommunityPrayerRequest::class, 'community_prayer_request_id');
    }
}
