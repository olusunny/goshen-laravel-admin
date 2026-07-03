<?php

namespace App\Services;

use App\Models\GoshenQuiz;
use App\Models\GoshenQuizAttempt;
use App\Models\GoshenQuizCelebrationMedia;
use App\Models\GoshenQuizQuestion;
use App\Models\GoshenQuizWinner;
use App\Models\MobileUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Personal\EventInstallments\Models\Ticket;
use RuntimeException;

class GoshenQuizService
{
    public function __construct(
        private readonly GoshenExperienceEligibility $eligibility,
        private readonly GoshenWalletService $wallets,
    ) {}

    public function listForUser(MobileUser $user): array
    {
        return GoshenQuiz::query()
            ->with([
                'event',
                'questions',
                'celebrationMedia',
                'attempts' => fn ($query) => $query->where('mobile_user_id', $user->id),
                'winners.mobileUser',
            ])
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('closes_at')->orWhere('closes_at', '>=', now());
            })
            ->latest('id')
            ->get()
            ->filter(fn (GoshenQuiz $quiz): bool => $this->eligibleTicket($quiz, $user) !== false)
            ->map(fn (GoshenQuiz $quiz): array => $this->quizPayload($quiz, $user))
            ->values()
            ->all();
    }

    public function quizPayload(GoshenQuiz $quiz, MobileUser $user, bool $includeQuestions = true): array
    {
        $quiz->loadMissing(['event', 'questions', 'attempts', 'winners.mobileUser', 'celebrationMedia']);
        $attempt = $quiz->attempts->firstWhere('mobile_user_id', $user->id);
        $eligible = $this->eligibleTicket($quiz, $user) !== false;
        $winnersVisible = (bool) $quiz->show_winners_immediately && $quiz->winners->isNotEmpty();

        return [
            'id' => $quiz->id,
            'title' => $quiz->title,
            'description' => $quiz->description,
            'start_instructions' => $quiz->start_instructions,
            'completion_message' => $quiz->completion_message,
            'audience' => $quiz->audience,
            'eligible_to_start' => $eligible,
            'auto_grade' => (bool) $quiz->auto_grade,
            'track_timing' => (bool) $quiz->track_timing,
            'timer_seconds' => (int) $quiz->timer_seconds,
            'winners_count' => (int) $quiz->winners_count,
            'show_prize' => (bool) $quiz->show_prize,
            'prize_label' => $quiz->show_prize ? $quiz->prize_label : null,
            'wallet_prize_enabled' => (bool) $quiz->wallet_prize_enabled,
            'show_winners_immediately' => (bool) $quiz->show_winners_immediately,
            'celebration_enabled' => (bool) $quiz->celebration_enabled,
            'opens_at' => $quiz->opens_at?->toIso8601String(),
            'closes_at' => $quiz->closes_at?->toIso8601String(),
            'event' => [
                'id' => $quiz->event?->id,
                'public_id' => $quiz->event?->public_id,
                'name' => $quiz->event?->name,
            ],
            'attempt' => $attempt ? $this->attemptPayload($attempt) : null,
            'questions' => $includeQuestions
                ? $quiz->questions->map(fn (GoshenQuizQuestion $question): array => $this->questionPayload($question))->values()->all()
                : [],
            'winners' => $winnersVisible
                ? $quiz->winners->map(fn (GoshenQuizWinner $winner): array => $this->winnerPayload($winner))->values()->all()
                : [],
            'celebration_media' => $winnersVisible && $quiz->celebration_enabled && $quiz->celebrationMedia
                ? $this->celebrationMediaPayload($quiz->celebrationMedia)
                : null,
        ];
    }

    public function start(GoshenQuiz $quiz, MobileUser $user): GoshenQuizAttempt
    {
        $quiz->loadMissing(['event', 'questions']);
        $ticket = $this->eligibleTicket($quiz, $user);
        if ($ticket === false) {
            throw ValidationException::withMessages([
                'quiz' => 'You are not eligible to start this quiz.',
            ]);
        }

        if (! $this->quizIsOpen($quiz)) {
            throw ValidationException::withMessages([
                'quiz' => 'This quiz is not currently available.',
            ]);
        }

        return DB::transaction(function () use ($quiz, $user, $ticket): GoshenQuizAttempt {
            $attempt = GoshenQuizAttempt::query()
                ->where('quiz_id', $quiz->id)
                ->where('mobile_user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($attempt) {
                return $attempt;
            }

            $now = now();

            return GoshenQuizAttempt::query()->create([
                'quiz_id' => $quiz->id,
                'event_id' => $quiz->event_id,
                'mobile_user_id' => $user->id,
                'booking_id' => $ticket instanceof Ticket ? $ticket->booking_id : null,
                'ticket_id' => $ticket instanceof Ticket ? $ticket->id : null,
                'status' => GoshenQuizAttempt::STATUS_STARTED,
                'started_at' => $now,
                'due_at' => $quiz->track_timing ? $now->copy()->addSeconds(max(1, (int) $quiz->timer_seconds)) : null,
                'max_score' => $this->maxScore($quiz),
                'total_questions' => $quiz->questions->count(),
            ]);
        });
    }

    public function submit(GoshenQuiz $quiz, MobileUser $user, array $answers): GoshenQuizAttempt
    {
        $quiz->loadMissing(['event', 'questions']);
        if (! $this->quizIsOpen($quiz)) {
            throw ValidationException::withMessages([
                'quiz' => 'This quiz is no longer accepting submissions.',
            ]);
        }

        $timedOut = false;

        $attempt = DB::transaction(function () use ($quiz, $user, $answers, &$timedOut): GoshenQuizAttempt {
            $attempt = GoshenQuizAttempt::query()
                ->where('quiz_id', $quiz->id)
                ->where('mobile_user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $attempt) {
                throw ValidationException::withMessages([
                    'quiz' => 'Please start the quiz before submitting.',
                ]);
            }

            if ($attempt->status === GoshenQuizAttempt::STATUS_SUBMITTED) {
                return $attempt;
            }

            if ($attempt->due_at && $attempt->due_at->lt(now())) {
                $attempt->forceFill([
                    'status' => GoshenQuizAttempt::STATUS_TIMED_OUT,
                    'timed_out_at' => now(),
                    'metadata' => array_merge($attempt->metadata ?? [], [
                        'timeout_enforced_at' => now()->toIso8601String(),
                    ]),
                ])->save();

                $timedOut = true;

                return $attempt->fresh();
            }

            $result = $this->score($quiz, $answers);
            $elapsed = $attempt->started_at
                ? max(0, $attempt->started_at->diffInSeconds(now()))
                : null;

            $attempt->forceFill([
                'status' => GoshenQuizAttempt::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'score' => $quiz->auto_grade ? $result['score'] : null,
                'max_score' => $result['max_score'],
                'correct_count' => $result['correct_count'],
                'answered_count' => $result['answered_count'],
                'total_questions' => $quiz->questions->count(),
                'elapsed_seconds' => $elapsed,
                'answers' => $result['answers'],
            ])->save();

            return $attempt->fresh(['quiz', 'mobileUser']);
        });

        if ($timedOut) {
            throw ValidationException::withMessages([
                'quiz' => 'The quiz timer has ended. Your attempt has timed out.',
            ]);
        }

        if ($quiz->auto_grade && $quiz->auto_select_winners) {
            $this->syncWinners($quiz);
        }

        return $attempt;
    }

    public function syncWinners(GoshenQuiz $quiz): void
    {
        DB::transaction(function () use ($quiz): void {
            $quiz = GoshenQuiz::query()
                ->with('winners')
                ->whereKey($quiz->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($quiz->winners->contains(fn (GoshenQuizWinner $winner): bool => $winner->wallet_prize_status === GoshenQuizWinner::WALLET_PRIZE_PAID)) {
                return;
            }

            $quiz->winners()->delete();

            $attempts = GoshenQuizAttempt::query()
                ->where('quiz_id', $quiz->id)
                ->where('status', GoshenQuizAttempt::STATUS_SUBMITTED)
                ->orderByDesc('score')
                ->orderBy('elapsed_seconds')
                ->orderBy('submitted_at')
                ->limit(max(1, (int) $quiz->winners_count))
                ->get();

            foreach ($attempts as $index => $attempt) {
                $quiz->winners()->create([
                    'attempt_id' => $attempt->id,
                    'mobile_user_id' => $attempt->mobile_user_id,
                    'rank' => $index + 1,
                    'score' => $attempt->score,
                    'elapsed_seconds' => $attempt->elapsed_seconds,
                    'selected_at' => now(),
                    'prize_label' => $quiz->show_prize ? $quiz->prize_label : null,
                    'wallet_prize_amount' => $quiz->wallet_prize_enabled ? $quiz->wallet_prize_amount : null,
                    'wallet_prize_currency' => strtoupper((string) ($quiz->wallet_prize_currency ?: 'GBP')),
                    'wallet_prize_status' => $quiz->wallet_prize_enabled
                        ? GoshenQuizWinner::WALLET_PRIZE_PENDING
                        : GoshenQuizWinner::WALLET_PRIZE_NOT_CONFIGURED,
                ]);
            }
        });
    }

    public function payWalletPrize(GoshenQuizWinner $winner, MobileUser $sponsor): GoshenQuizWinner
    {
        if (! $sponsor->canUseCommunity()) {
            throw new RuntimeException('Please verify your account before funding a quiz prize.');
        }

        return DB::transaction(function () use ($winner, $sponsor): GoshenQuizWinner {
            $quiz = GoshenQuiz::query()
                ->whereKey($winner->quiz_id)
                ->lockForUpdate()
                ->firstOrFail();

            $winner = GoshenQuizWinner::query()
                ->with(['mobileUser', 'quiz'])
                ->whereKey($winner->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $winner->quiz_id !== (int) $quiz->id) {
                throw new RuntimeException('This quiz winner could not be verified.');
            }

            if ($winner->wallet_prize_status === GoshenQuizWinner::WALLET_PRIZE_PAID) {
                return $winner;
            }

            $amount = round((float) $winner->wallet_prize_amount, 2);
            if ($winner->wallet_prize_status !== GoshenQuizWinner::WALLET_PRIZE_PENDING || $amount <= 0) {
                throw new RuntimeException('This quiz winner does not have a pending wallet prize.');
            }

            $result = $this->wallets->transfer(
                $this->wallets->walletFor($sponsor),
                (string) $winner->mobileUser->email,
                $amount,
                strtoupper((string) ($winner->wallet_prize_currency ?: 'GBP')),
                'Quiz prize: ' . $winner->quiz->title,
            );

            $recipientEntry = $result['recipient_entry'] ?? null;

            $winner->forceFill([
                'wallet_prize_status' => GoshenQuizWinner::WALLET_PRIZE_PAID,
                'wallet_sponsor_mobile_user_id' => $sponsor->id,
                'wallet_ledger_entry_id' => $recipientEntry?->id,
                'wallet_transfer_reference' => $result['reference'] ?? null,
                'metadata' => array_merge($winner->metadata ?? [], [
                    'wallet_prize_paid_at' => now()->toIso8601String(),
                    'wallet_prize_sponsor_id' => $sponsor->id,
                    'wallet_prize_sponsor_name' => $sponsor->name,
                ]),
            ])->save();

            return $winner->fresh(['mobileUser', 'walletSponsor', 'walletLedgerEntry']);
        });
    }

    public function attemptPayload(GoshenQuizAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'status' => $attempt->status,
            'started_at' => $attempt->started_at?->toIso8601String(),
            'due_at' => $attempt->due_at?->toIso8601String(),
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            'timed_out_at' => $attempt->timed_out_at?->toIso8601String(),
            'score' => $attempt->score !== null ? (float) $attempt->score : null,
            'max_score' => $attempt->max_score !== null ? (float) $attempt->max_score : null,
            'correct_count' => (int) $attempt->correct_count,
            'answered_count' => (int) $attempt->answered_count,
            'total_questions' => (int) $attempt->total_questions,
            'elapsed_seconds' => $attempt->elapsed_seconds,
            'answers' => $attempt->answers ?: (object) [],
        ];
    }

    public function winnerPayload(GoshenQuizWinner $winner): array
    {
        $winner->loadMissing('mobileUser');

        return [
            'id' => $winner->id,
            'rank' => (int) $winner->rank,
            'name' => $winner->mobileUser?->name ?: 'Winner',
            'score' => $winner->score !== null ? (float) $winner->score : null,
            'elapsed_seconds' => $winner->elapsed_seconds,
            'selected_at' => $winner->selected_at?->toIso8601String(),
            'prize_label' => $winner->prize_label,
            'wallet_prize_amount' => $winner->wallet_prize_amount !== null ? (float) $winner->wallet_prize_amount : null,
            'wallet_prize_currency' => $winner->wallet_prize_currency,
            'wallet_prize_status' => $winner->wallet_prize_status,
        ];
    }

    private function questionPayload(GoshenQuizQuestion $question): array
    {
        return [
            'id' => $question->id,
            'prompt' => $question->prompt,
            'type' => $question->type,
            'points' => (float) $question->points,
            'is_required' => (bool) $question->is_required,
            'sort_order' => (int) $question->sort_order,
            'options' => collect($question->options ?: [])
                ->map(fn (mixed $option): array => [
                    'label' => $this->optionLabel($option),
                    'value' => $this->optionValue($option),
                ])
                ->filter(fn (array $option): bool => $option['value'] !== '')
                ->values()
                ->all(),
        ];
    }

    private function celebrationMediaPayload(GoshenQuizCelebrationMedia $media): array
    {
        return [
            'id' => $media->id,
            'name' => $media->name,
            'description' => $media->description,
            'video_url' => $media->video_path ? $this->publicStorageUrl($media->video_path) : null,
            'image_urls' => collect($media->image_paths ?: [])
                ->map(fn (string $path): string => $this->publicStorageUrl($path))
                ->values()
                ->all(),
        ];
    }

    private function score(GoshenQuiz $quiz, array $rawAnswers): array
    {
        $answers = [];
        $score = 0.0;
        $correctCount = 0;
        $answeredCount = 0;
        $maxScore = $this->maxScore($quiz);

        foreach ($quiz->questions as $question) {
            $key = (string) $question->id;
            $answer = $this->decodedAnswer($rawAnswers[$key] ?? $rawAnswers[$question->id] ?? null);
            $blank = blank($answer) || (is_array($answer) && $answer === []);
            if ($blank) {
                continue;
            }

            $answeredCount++;
            $correct = $this->answerIsCorrect($question, $answer);
            if ($correct) {
                $correctCount++;
                $score += (float) $question->points;
            }

            $answers[$key] = [
                'question_id' => $question->id,
                'prompt' => $question->prompt,
                'type' => $question->type,
                'answer' => $answer,
                'is_correct' => $quiz->auto_grade ? $correct : null,
                'points_awarded' => $quiz->auto_grade && $correct ? (float) $question->points : 0,
            ];
        }

        return [
            'answers' => $answers,
            'score' => round($score, 2),
            'max_score' => round($maxScore, 2),
            'correct_count' => $correctCount,
            'answered_count' => $answeredCount,
        ];
    }

    private function answerIsCorrect(GoshenQuizQuestion $question, mixed $answer): bool
    {
        if ($question->type === GoshenQuizQuestion::TYPE_SHORT_TEXT) {
            $accepted = collect(data_get($question->settings, 'accepted_answers', []))
                ->map(fn ($value): string => str($value)->lower()->trim()->toString())
                ->filter();

            return $accepted->isNotEmpty() && $accepted->contains(str((string) $answer)->lower()->trim()->toString());
        }

        $correct = collect($question->options ?: [])
            ->filter(fn (mixed $option): bool => filter_var(is_array($option) ? ($option['is_correct'] ?? false) : false, FILTER_VALIDATE_BOOLEAN))
            ->map(fn (mixed $option): string => $this->optionValue($option))
            ->filter()
            ->sort()
            ->values()
            ->all();

        if ($correct === []) {
            return false;
        }

        $given = collect(is_array($answer) ? $answer : [$answer])
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->sort()
            ->values()
            ->all();

        return $given === $correct;
    }

    private function maxScore(GoshenQuiz $quiz): float
    {
        return round((float) $quiz->questions->sum(fn (GoshenQuizQuestion $question): float => (float) $question->points), 2);
    }

    private function eligibleTicket(GoshenQuiz $quiz, MobileUser $user): Ticket|bool|null
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

        if ($quiz->audience !== GoshenQuiz::AUDIENCE_GOSHEN_CHECKED_IN) {
            return null;
        }

        if (! $quiz->event) {
            return false;
        }

        return $this->eligibility->eligibleTicketFor($user, $quiz->event) ?: false;
    }

    private function quizIsOpen(GoshenQuiz $quiz): bool
    {
        if (! $quiz->is_active) {
            return false;
        }

        $now = Carbon::now();

        return (! $quiz->opens_at || $quiz->opens_at->lte($now))
            && (! $quiz->closes_at || $quiz->closes_at->gte($now));
    }

    private function decodedAnswer(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $trimmed;
    }

    private function optionValue(mixed $option): string
    {
        if (is_array($option)) {
            $value = trim((string) ($option['value'] ?? ''));

            return $value !== '' ? $value : trim((string) ($option['label'] ?? ''));
        }

        return trim((string) $option);
    }

    private function optionLabel(mixed $option): string
    {
        if (is_array($option)) {
            $label = trim((string) ($option['label'] ?? ''));

            return $label !== '' ? $label : $this->optionValue($option);
        }

        return trim((string) $option);
    }

    private function publicStorageUrl(string $path): string
    {
        $url = Storage::disk('public')->url($path);

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : url($url);
    }
}
