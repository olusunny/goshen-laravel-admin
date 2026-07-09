<?php

namespace Tests\Feature;

use App\Models\GoshenQuiz;
use App\Models\GoshenQuizAttempt;
use App\Models\GoshenQuizQuestion;
use App\Models\GoshenQuizWinner;
use App\Models\GoshenWallet;
use App\Models\MobileUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoshenQuizApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_start_and_submit_incomplete_quiz_with_auto_winner_selection(): void
    {
        $member = $this->member('quiz-member@example.test');
        $token = $member->issueApiToken();
        $quiz = $this->quiz();
        $first = $this->question($quiz, 'Who built the ark?', [
            ['label' => 'Noah', 'value' => 'noah', 'is_correct' => true],
            ['label' => 'Moses', 'value' => 'moses', 'is_correct' => false],
        ]);
        $this->question($quiz, 'Choose every fruit', [
            ['label' => 'Apple', 'value' => 'apple', 'is_correct' => true],
            ['label' => 'Stone', 'value' => 'stone', 'is_correct' => false],
        ]);

        $this->postJson("/api/goshen-quizzes/{$quiz->id}/start", [
            'data' => ['api_token' => $token],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('attempt.status', GoshenQuizAttempt::STATUS_STARTED);

        $this->postJson("/api/goshen-quizzes/{$quiz->id}/submit", [
            'data' => [
                'api_token' => $token,
                'answers' => [
                    (string) $first->id => 'noah',
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('attempt.status', GoshenQuizAttempt::STATUS_SUBMITTED)
            ->assertJsonPath('attempt.answered_count', 1)
            ->assertJsonPath('attempt.correct_count', 1);

        $this->assertSame(1, GoshenQuizWinner::query()->where('quiz_id', $quiz->id)->count());
        $this->assertSame($member->id, GoshenQuizWinner::query()->firstOrFail()->mobile_user_id);
    }

    public function test_tie_breaker_prefers_faster_submission(): void
    {
        $slow = $this->member('slow@example.test');
        $fast = $this->member('fast@example.test');
        $quiz = $this->quiz(['winners_count' => 1]);
        $question = $this->question($quiz, 'First book?', [
            ['label' => 'Genesis', 'value' => 'genesis', 'is_correct' => true],
            ['label' => 'John', 'value' => 'john', 'is_correct' => false],
        ]);

        $slowAttempt = GoshenQuizAttempt::query()->create([
            'quiz_id' => $quiz->id,
            'mobile_user_id' => $slow->id,
            'status' => GoshenQuizAttempt::STATUS_SUBMITTED,
            'started_at' => now()->subSeconds(30),
            'due_at' => now()->addMinutes(4),
            'submitted_at' => now()->subSeconds(1),
            'score' => 1,
            'max_score' => 1,
            'correct_count' => 1,
            'answered_count' => 1,
            'total_questions' => 1,
            'elapsed_seconds' => 29,
            'answers' => [(string) $question->id => ['answer' => 'genesis']],
        ]);

        $fastAttempt = GoshenQuizAttempt::query()->create([
            'quiz_id' => $quiz->id,
            'mobile_user_id' => $fast->id,
            'status' => GoshenQuizAttempt::STATUS_SUBMITTED,
            'started_at' => now()->subSeconds(12),
            'due_at' => now()->addMinutes(4),
            'submitted_at' => now(),
            'score' => 1,
            'max_score' => 1,
            'correct_count' => 1,
            'answered_count' => 1,
            'total_questions' => 1,
            'elapsed_seconds' => 12,
            'answers' => [(string) $question->id => ['answer' => 'genesis']],
        ]);

        app(\App\Services\GoshenQuizService::class)->syncWinners($quiz);

        $winner = GoshenQuizWinner::query()->firstOrFail();
        $this->assertSame($fastAttempt->id, $winner->attempt_id);
        $this->assertNotSame($slowAttempt->id, $winner->attempt_id);
    }

    public function test_submit_after_due_time_marks_attempt_timed_out(): void
    {
        $member = $this->member('late@example.test');
        $token = $member->issueApiToken();
        $quiz = $this->quiz(['timer_seconds' => 30]);
        $question = $this->question($quiz, 'Answer?', [
            ['label' => 'Yes', 'value' => 'yes', 'is_correct' => true],
            ['label' => 'No', 'value' => 'no', 'is_correct' => false],
        ]);

        GoshenQuizAttempt::query()->create([
            'quiz_id' => $quiz->id,
            'mobile_user_id' => $member->id,
            'status' => GoshenQuizAttempt::STATUS_STARTED,
            'started_at' => now()->subMinutes(2),
            'due_at' => now()->subSecond(),
            'max_score' => 1,
            'total_questions' => 1,
        ]);

        $this->postJson("/api/goshen-quizzes/{$quiz->id}/submit", [
            'data' => [
                'api_token' => $token,
                'answers' => [(string) $question->id => 'yes'],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The quiz timer has ended. Your attempt has timed out.');

        $this->assertSame(
            GoshenQuizAttempt::STATUS_TIMED_OUT,
            GoshenQuizAttempt::query()->where('quiz_id', $quiz->id)->firstOrFail()->status,
        );
    }

    public function test_active_future_quiz_is_listed_but_cannot_start_yet(): void
    {
        $member = $this->member('upcoming@example.test');
        $token = $member->issueApiToken();
        $quiz = $this->quiz([
            'opens_at' => now()->addHour(),
        ]);
        $this->question($quiz, 'Ready?', [
            ['label' => 'Yes', 'value' => 'yes', 'is_correct' => true],
            ['label' => 'No', 'value' => 'no', 'is_correct' => false],
        ]);

        $this->postJson('/api/goshen-quizzes', [
            'data' => ['api_token' => $token],
        ])
            ->assertOk()
            ->assertJsonPath('data.0.id', $quiz->id)
            ->assertJsonPath('data.0.title', 'Goshen Bible Quiz');

        $this->postJson("/api/goshen-quizzes/{$quiz->id}/start", [
            'data' => ['api_token' => $token],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This quiz is not currently available.');
    }

    public function test_only_authorized_quiz_prize_managers_can_fund_winner_wallet_prize(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $quiz = $this->quiz([
            'wallet_prize_enabled' => true,
            'wallet_prize_amount' => 25,
            'wallet_prize_currency' => 'GBP',
        ]);

        $winner = $this->pendingWinner($quiz, 1);
        $memberSponsor = $this->member('member-sponsor@example.test');
        $this->wallet($memberSponsor, 100);
        $this->postJson("/api/goshen-quizzes/{$quiz->id}/winners/{$winner->id}/wallet-prize", [
            'data' => ['api_token' => $memberSponsor->issueApiToken()],
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Your account is not authorized to fund quiz winner wallet prizes.');

        $this->assertSame(GoshenQuizWinner::WALLET_PRIZE_PENDING, $winner->fresh()->wallet_prize_status);

        foreach (['goshen_manager', 'retreat_manager', 'fundraising_manager'] as $index => $roleName) {
            $sponsor = $this->member("blocked-sponsor-{$index}@example.test");
            Role::findOrCreate($roleName, 'mobile');
            $sponsor->assignRole($roleName);
            $this->wallet($sponsor, 100);

            $this->postJson("/api/goshen-quizzes/{$quiz->id}/winners/{$winner->id}/wallet-prize", [
                'data' => ['api_token' => $sponsor->issueApiToken()],
            ])
                ->assertForbidden()
                ->assertJsonPath('message', 'Your account is not authorized to fund quiz winner wallet prizes.');
        }

        foreach ([
            'admin' => fn (MobileUser $sponsor) => $sponsor->assignRole(Role::findOrCreate('admin', 'mobile')),
            'event manager' => fn (MobileUser $sponsor) => $sponsor->assignRole(Role::findOrCreate('event_manager', 'mobile')),
            'quiz manager' => fn (MobileUser $sponsor) => $sponsor->assignRole(Role::findOrCreate('quiz_manager', 'mobile')),
            'quiz permission role' => function (MobileUser $sponsor): void {
                $permission = Permission::findOrCreate('manage_goshen_quiz', 'mobile');
                $role = Role::findOrCreate('quiz_resource_manager', 'mobile');
                $role->givePermissionTo($permission);
                $sponsor->assignRole($role);
            },
        ] as $label => $grantAccess) {
            $rank = GoshenQuizWinner::query()->where('quiz_id', $quiz->id)->count() + 1;
            $winner = $this->pendingWinner($quiz, $rank);
            $sponsor = $this->member(str_replace(' ', '-', $label) . '-sponsor@example.test');
            $grantAccess($sponsor);
            $wallet = $this->wallet($sponsor, 100);

            $this->postJson("/api/goshen-quizzes/{$quiz->id}/winners/{$winner->id}/wallet-prize", [
                'data' => ['api_token' => $sponsor->issueApiToken()],
            ])
                ->assertOk()
                ->assertJsonPath('status', 'ok')
                ->assertJsonPath('winner.wallet_prize_status', GoshenQuizWinner::WALLET_PRIZE_PAID);

            $this->assertSame('75.00', $wallet->fresh()->balance);
            $this->assertSame(GoshenQuizWinner::WALLET_PRIZE_PAID, $winner->fresh()->wallet_prize_status);
        }
    }

    public function test_authorized_quiz_manager_can_view_summary_and_update_settings(): void
    {
        $quiz = $this->quiz([
            'is_active' => false,
            'show_winners_immediately' => false,
            'winners_count' => 1,
        ]);
        $this->question($quiz, 'Who led Israel from Egypt?', [
            ['label' => 'Moses', 'value' => 'moses', 'is_correct' => true],
            ['label' => 'Joshua', 'value' => 'joshua', 'is_correct' => false],
        ]);
        $this->pendingWinner($quiz, 1);

        $member = $this->member('quiz-summary-member@example.test');
        $this->postJson('/api/goshen-quizzes/management/summary', [
            'data' => ['api_token' => $member->issueApiToken()],
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Your account is not authorized to manage Goshen quizzes.');

        $manager = $this->member('quiz-summary-manager@example.test');
        $manager->assignRole(Role::findOrCreate('quiz_manager', 'mobile'));

        $this->postJson('/api/goshen-quizzes/management/summary', [
            'data' => ['api_token' => $manager->issueApiToken()],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('data.totals.quizzes', 1)
            ->assertJsonPath('data.totals.winners', 1)
            ->assertJsonPath('data.quizzes.0.title', 'Goshen Bible Quiz')
            ->assertJsonPath('data.quizzes.0.selected_winners_count', 1);

        $this->postJson("/api/goshen-quizzes/{$quiz->id}/settings", [
            'data' => [
                'api_token' => $manager->fresh()->issueApiToken(),
                'is_active' => true,
                'show_winners_immediately' => true,
                'winners_count' => 2,
                'wallet_prize_currency' => 'gbp',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('quiz.is_active', true)
            ->assertJsonPath('quiz.show_winners_immediately', true)
            ->assertJsonPath('quiz.winners_count', 2)
            ->assertJsonPath('quiz.wallet_prize_currency', 'GBP');

        $this->assertTrue($quiz->fresh()->is_active);
    }

    private function quiz(array $overrides = []): GoshenQuiz
    {
        return GoshenQuiz::query()->create(array_merge([
            'title' => 'Goshen Bible Quiz',
            'is_active' => true,
            'audience' => GoshenQuiz::AUDIENCE_ALL_USERS,
            'auto_grade' => true,
            'auto_select_winners' => true,
            'track_timing' => true,
            'timer_seconds' => 300,
            'winners_count' => 1,
            'show_winners_immediately' => true,
        ], $overrides));
    }

    private function question(GoshenQuiz $quiz, string $prompt, array $options): GoshenQuizQuestion
    {
        return GoshenQuizQuestion::query()->create([
            'quiz_id' => $quiz->id,
            'prompt' => $prompt,
            'type' => GoshenQuizQuestion::TYPE_SINGLE_CHOICE,
            'options' => $options,
            'points' => 1,
            'sort_order' => GoshenQuizQuestion::query()->where('quiz_id', $quiz->id)->count() + 1,
        ]);
    }

    private function pendingWinner(GoshenQuiz $quiz, int $rank): GoshenQuizWinner
    {
        $winnerUser = $this->member("winner-{$rank}@example.test");
        $attempt = GoshenQuizAttempt::query()->create([
            'quiz_id' => $quiz->id,
            'mobile_user_id' => $winnerUser->id,
            'status' => GoshenQuizAttempt::STATUS_SUBMITTED,
            'started_at' => now()->subMinute(),
            'submitted_at' => now(),
            'score' => 1,
            'max_score' => 1,
            'correct_count' => 1,
            'answered_count' => 1,
            'total_questions' => 1,
            'elapsed_seconds' => 30,
        ]);

        return GoshenQuizWinner::query()->create([
            'quiz_id' => $quiz->id,
            'attempt_id' => $attempt->id,
            'mobile_user_id' => $winnerUser->id,
            'rank' => $rank,
            'score' => 1,
            'elapsed_seconds' => 30,
            'selected_at' => now(),
            'wallet_prize_amount' => 25,
            'wallet_prize_currency' => 'GBP',
            'wallet_prize_status' => GoshenQuizWinner::WALLET_PRIZE_PENDING,
        ]);
    }

    private function member(string $email): MobileUser
    {
        return MobileUser::query()->create([
            'name' => 'Quiz Member',
            'email' => $email,
            'phone' => '+447700900000',
            'password' => 'secret',
            'is_verified' => true,
            'is_blocked' => false,
            'is_deleted' => false,
        ]);
    }

    private function wallet(MobileUser $member, float $balance): GoshenWallet
    {
        return GoshenWallet::query()->create([
            'mobile_user_id' => $member->id,
            'currency' => 'GBP',
            'balance' => $balance,
        ]);
    }
}
