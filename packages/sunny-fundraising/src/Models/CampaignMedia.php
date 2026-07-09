<?php

namespace Sunny\Fundraising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CampaignMedia extends Model
{
    protected $table = 'fundraising_campaign_media';

    protected $guarded = [];

    protected $casts = [
        'is_feature' => 'boolean',
        'metadata' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function publicUrl(): ?string
    {
        if ($this->url) {
            return $this->url;
        }

        return $this->path ? Storage::disk($this->disk ?: 'public')->url($this->path) : null;
    }
}
