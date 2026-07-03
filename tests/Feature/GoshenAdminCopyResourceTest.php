<?php

namespace Tests\Feature;

use App\Filament\Resources\GoshenExperienceSurveyResource;
use App\Filament\Resources\GoshenQuizResource;
use App\Models\GoshenExperienceQuestion;
use App\Models\GoshenExperienceSurvey;
use App\Models\GoshenQuiz;
use App\Models\GoshenQuizQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Personal\EventInstallments\Enums\EventType;
use Personal\EventInstallments\Models\Event;
use Tests\TestCase;

class GoshenAdminCopyResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_copy_quiz_with_questions_without_copying_live_state(): void
    {
        $quiz = GoshenQuiz::query()->create([
            'title' => 'Sunday Bible Quiz',
            'description' => 'Original quiz',
            'is_active' => true,
            'audience' => GoshenQuiz::AUDIENCE_ALL_USERS,
            'auto_grade' => true,
            'auto_select_winners' => true,
            'track_timing' => true,
            'timer_seconds' => 300,
            'winners_count' => 3,
            'show_prize' => true,
            'prize_label' => 'Wallet prize',
            'wallet_prize_enabled' => true,
            'wallet_prize_amount' => 10,
            'wallet_prize_currency' => 'GBP',
            'opens_at' => now()->subMinute(),
            'closes_at' => now()->addDay(),
            'metadata' => ['round' => 'main'],
        ]);

        $question = GoshenQuizQuestion::query()->create([
            'quiz_id' => $quiz->id,
            'prompt' => 'Who built the ark?',
            'type' => GoshenQuizQuestion::TYPE_SINGLE_CHOICE,
            'options' => [
                ['label' => 'Noah', 'value' => 'noah', 'is_correct' => true],
                ['label' => 'Moses', 'value' => 'moses', 'is_correct' => false],
            ],
            'points' => 2,
            'is_required' => true,
            'sort_order' => 1,
            'explanation' => 'Genesis records Noah building the ark.',
            'settings' => ['accepted_answers' => ['Noah']],
        ]);

        $tableRecord = GoshenQuiz::query()
            ->withCount(['questions', 'attempts'])
            ->findOrFail($quiz->id);

        $copy = GoshenQuizResource::copyQuiz($tableRecord);
        $copiedQuestion = $copy->questions()->firstOrFail();

        $this->assertNotSame($quiz->id, $copy->id);
        $this->assertSame('Sunday Bible Quiz (Copy)', $copy->title);
        $this->assertFalse($copy->is_active);
        $this->assertSame(1, $copy->questions()->count());
        $this->assertNotSame($question->id, $copiedQuestion->id);
        $this->assertSame($copy->id, $copiedQuestion->quiz_id);
        $this->assertSame($question->prompt, $copiedQuestion->prompt);
        $this->assertSame($question->options, $copiedQuestion->options);
        $this->assertSame($question->settings, $copiedQuestion->settings);
    }

    public function test_admin_can_copy_experience_survey_with_question_options_without_responses(): void
    {
        $event = $this->goshenEvent();
        $survey = GoshenExperienceSurvey::query()->create([
            'event_id' => $event->id,
            'title' => 'Retreat T-shirt Survey',
            'description' => 'Pick your shirt',
            'is_active' => true,
            'allow_all_authenticated_users' => true,
            'allow_audio' => true,
            'allow_video' => false,
            'reminder_enabled' => true,
            'reminder_interval_minutes' => 60,
            'opens_at' => now()->subMinute(),
            'closes_at' => now()->addDay(),
            'thank_you_message' => 'Thank you',
            'metadata' => ['kind' => 'merch'],
        ]);

        $question = GoshenExperienceQuestion::query()->create([
            'survey_id' => $survey->id,
            'prompt' => 'Which T-shirt do you want?',
            'type' => GoshenExperienceQuestion::TYPE_IMAGE_CHOICE,
            'options' => null,
            'conditional_logic' => ['enabled' => false],
            'settings' => [
                'image_options' => [
                    [
                        'label' => 'Round neck',
                        'value' => 'round-neck',
                        'image_path' => 'goshen/experience/options/round-neck.png',
                    ],
                ],
            ],
            'is_required' => true,
            'sort_order' => 1,
        ]);

        $tableRecord = GoshenExperienceSurvey::query()
            ->withCount('responses')
            ->findOrFail($survey->id);

        $copy = GoshenExperienceSurveyResource::copySurvey($tableRecord);
        $copiedQuestion = $copy->questions()->firstOrFail();

        $this->assertNotSame($survey->id, $copy->id);
        $this->assertSame('Retreat T-shirt Survey (Copy)', $copy->title);
        $this->assertFalse($copy->is_active);
        $this->assertSame(0, $copy->responses()->count());
        $this->assertSame(1, $copy->questions()->count());
        $this->assertNotSame($question->id, $copiedQuestion->id);
        $this->assertSame($copy->id, $copiedQuestion->survey_id);
        $this->assertSame($question->prompt, $copiedQuestion->prompt);
        $this->assertSame($question->conditional_logic, $copiedQuestion->conditional_logic);
        $this->assertSame($question->settings, $copiedQuestion->settings);
    }

    private function goshenEvent(): Event
    {
        return Event::query()->create([
            'name' => 'Goshen Retreat 2026',
            'slug' => 'goshen-retreat-2026',
            'type' => EventType::Sequential,
            'timezone' => 'Africa/Lagos',
            'status' => 'published',
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addMonth(),
            'settings' => [
                'module' => 'goshen_retreat',
            ],
        ]);
    }
}
