<?php

namespace App\Services;

use App\Models\CommunityPrayerRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PrayerModerationNotifier
{
    public function notifyAutoHidden(CommunityPrayerRequest $prayerRequest): void
    {
        $moderators = User::role(['moderator', 'super_admin'])
            ->whereNotNull('email')
            ->get()
            ->unique('email');

        if ($moderators->isEmpty()) {
            return;
        }

        $submitter = $prayerRequest->mobileUser?->email ?? 'Unknown mobile user';
        $subject = 'Prayer request auto-hidden after community flags';
        $body = implode("\n", [
            'An interactive prayer request has been automatically hidden after receiving 3 unique community flags.',
            '',
            "Prayer request ID: {$prayerRequest->id}",
            "Submitted by: {$submitter}",
            "Type: {$prayerRequest->type}",
            "Flags: {$prayerRequest->flags_count}",
            "Hidden reason: {$prayerRequest->hidden_reason}",
            '',
            'Please sign in to the MFM Triumphant Church admin panel, review the request, then either restore it or keep it hidden/delete it as appropriate.',
        ]);

        foreach ($moderators as $moderator) {
            try {
                app(DynamicSmtpMailer::class)->sendRaw($moderator->email, $subject, $body);
            } catch (\Throwable $exception) {
                Log::warning('Unable to email prayer moderation alert.', [
                    'prayer_request_id' => $prayerRequest->id,
                    'moderator_email' => $moderator->email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
