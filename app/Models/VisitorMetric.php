<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class VisitorMetric extends Model
{
    protected $guarded = [];

    protected $casts = [
        'visited_at' => 'datetime',
    ];

    public function scopeRealTraffic(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $query): void {
                $query->whereNull('user_agent')
                    ->orWhere('user_agent', '!=', 'Seed analytics');
            })
            ->where(function (Builder $query): void {
                $query->whereNull('session_key')
                    ->orWhere('session_key', 'not like', 'seed-%');
            });
    }

    public function scopeFlutterApiTraffic(Builder $query): Builder
    {
        $paths = [
            '/discover',
            '/verse_of_day',
            '/fetch_categories',
            '/gallery_images',
            '/fetch_media',
            '/fetch_categories_media',
            '/search',
            '/devotionals',
            '/fetch_events',
            '/fetch_prayerpoints',
            '/submitprayer',
            '/fetch_inbox',
            '/delete_inbox',
            '/fetch_hymns',
            '/getBibleVersions',
            '/church_branches',
            '/church_pastors',
            '/church_groups',
            '/church_groups/manage',
            '/transportation_arrangements',
            '/discoverLivestreams',
            '/discoverTrends',
            '/getmediatotallikesandcommentsviews',
            '/update_media_total_views',
            '/likeunlikemedia',
            '/loginUser',
            '/registerUser',
            '/googleAuth',
            '/verifyMobileEmail',
            '/resendMobileVerification',
            '/requestPasswordReset',
            '/resetMobilePassword',
            '/storefcmtoken',
            '/updateProfile',
            '/submit_suggestion',
            '/submit_contact',
            '/content_page/%',
            '/prayer-community%',
            '/testimonies%',
        ];

        return $query->where(function (Builder $query) use ($paths): void {
            $query->where('channel', 'api');

            foreach ($paths as $path) {
                str_contains($path, '%')
                    ? $query->orWhere('path', 'like', $path)
                    : $query->orWhere('path', $path);
            }
        });
    }

    public function scopeAuthenticatedMobileTraffic(Builder $query): Builder
    {
        return $query->whereNotNull('mobile_user_id');
    }

    public function mobileUser(): BelongsTo
    {
        return $this->belongsTo(MobileUser::class);
    }
}
