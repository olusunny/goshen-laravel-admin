<?php

namespace App\Console\Commands;

use App\Models\GoshenExperienceReminder;
use App\Models\GoshenExperienceResponse;
use App\Models\GoshenExperienceSurvey;
use App\Services\GoshenExperienceEligibility;
use App\Services\GoshenRetreatNotificationService;
use Illuminate\Console\Command;

class ProcessGoshenExperienceReminders extends Command
{
    protected $signature = 'goshen:process-experience-reminders {--limit=100}';

    protected $description = 'Send friendly Goshen Experience survey reminders to checked-in attendees who have not responded.';

    public function handle(
        GoshenExperienceEligibility $eligibility,
        GoshenRetreatNotificationService $notifications,
    ): int {
        $limit = max(1, (int) $this->option('limit'));
        $sent = 0;

        $surveys = GoshenExperienceSurvey::query()
            ->with('event')
            ->open()
            ->where('reminder_enabled', true)
            ->get();

        foreach ($surveys as $survey) {
            if ($sent >= $limit) {
                break;
            }

            $respondedUserIds = GoshenExperienceResponse::query()
                ->where('survey_id', $survey->id)
                ->pluck('mobile_user_id');

            $users = $eligibility->checkedInMobileUsersFor($survey->event)
                ->whereNotIn('id', $respondedUserIds)
                ->limit($limit - $sent)
                ->get();

            foreach ($users as $user) {
                $reminder = GoshenExperienceReminder::query()->firstOrCreate([
                    'survey_id' => $survey->id,
                    'mobile_user_id' => $user->id,
                ]);

                if ($reminder->completed_at) {
                    continue;
                }

                $interval = max(30, (int) $survey->reminder_interval_minutes);
                if ($reminder->last_sent_at && $reminder->last_sent_at->gt(now()->subMinutes($interval))) {
                    continue;
                }

                $body = implode("\n\n", [
                    'Hello ' . ($user->name ?: 'beloved') . ',',
                    'Thank you for attending ' . ($survey->event?->name ?: 'Goshen Retreat') . '. We would love to hear what the Lord did for you and how the retreat blessed your heart.',
                    'Kindly open the Goshen Experience page in the app and share your testimony, audio, video, or feedback when you can. Your response helps the ministry serve you and others better.',
                    'May the Lord preserve every blessing you received and make your testimony permanent. Amen.',
                ]);

                $notifications->notifyUser(
                    $user,
                    'Share your Goshen Experience',
                    $body,
                    'events',
                    email: true,
                    push: true,
                );

                $reminder->forceFill([
                    'last_sent_at' => now(),
                    'sent_count' => $reminder->sent_count + 1,
                ])->save();

                $sent++;
            }
        }

        $this->info("Sent {$sent} Goshen Experience reminder(s).");

        return self::SUCCESS;
    }
}
