<?php

namespace App\Http\Controllers\Api;

use App\Models\GoshenQuiz;
use App\Models\GoshenQuizAttempt;
use App\Models\GoshenQuizWinner;
use App\Models\MobileUser;
use App\Services\GoshenQuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GoshenQuizController extends Controller
{
    public function __construct(private readonly GoshenQuizService $quizzes) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Quizzes loaded.',
            'data' => $this->quizzes->listForUser($user),
        ]);
    }

    public function show(Request $request, GoshenQuiz $quiz): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $quiz->loadMissing(['event', 'questions', 'winners.mobileUser', 'celebrationMedia', 'attempts' => fn ($query) => $query->where('mobile_user_id', $user->id)]);

        return response()->json([
            'status' => 'ok',
            'data' => $this->quizzes->quizPayload($quiz, $user),
        ]);
    }

    public function start(Request $request, GoshenQuiz $quiz): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        try {
            $attempt = $this->quizzes->start($quiz, $user);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Quiz started.',
            'attempt' => $this->quizzes->attemptPayload($attempt),
            'quiz' => $this->quizzes->quizPayload($quiz->fresh(['event', 'questions', 'winners.mobileUser', 'celebrationMedia']), $user),
        ]);
    }

    public function submit(Request $request, GoshenQuiz $quiz): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $data = $this->payload($request);
        $validator = Validator::make($data, [
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $attempt = $this->quizzes->submit($quiz, $user, $validator->validated()['answers'] ?? []);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }

        $quiz = $quiz->fresh(['event', 'questions', 'winners.mobileUser', 'celebrationMedia', 'attempts' => fn ($query) => $query->where('mobile_user_id', $user->id)]);

        return response()->json([
            'status' => 'ok',
            'message' => $quiz->completion_message ?: 'Your quiz has been submitted.',
            'attempt' => $this->quizzes->attemptPayload($attempt),
            'quiz' => $this->quizzes->quizPayload($quiz, $user),
        ]);
    }

    public function winners(Request $request, GoshenQuiz $quiz): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $quiz->loadMissing(['winners.mobileUser', 'celebrationMedia']);

        if (! $quiz->show_winners_immediately) {
            return response()->json([
                'status' => 'error',
                'message' => 'Winners are not available yet.',
            ], 403);
        }

        return response()->json([
            'status' => 'ok',
            'data' => [
                'winners' => $quiz->winners
                    ->map(fn (GoshenQuizWinner $winner): array => $this->quizzes->winnerPayload($winner))
                    ->values(),
                'celebration_media' => $this->quizzes->quizPayload($quiz, $user, false)['celebration_media'] ?? null,
            ],
        ]);
    }

    public function managementSummary(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (! $this->canManageQuizzes($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is not authorized to manage Goshen quizzes.',
            ], 403);
        }

        $quizzes = GoshenQuiz::query()
            ->with(['event', 'questions', 'winners.mobileUser'])
            ->withCount([
                'questions',
                'attempts',
                'winners as selected_winners_count',
                'attempts as submitted_attempts_count' => fn ($query) => $query->where('status', GoshenQuizAttempt::STATUS_SUBMITTED),
                'attempts as timed_out_attempts_count' => fn ($query) => $query->where('status', GoshenQuizAttempt::STATUS_TIMED_OUT),
            ])
            ->latest('id')
            ->limit(100)
            ->get();

        $quizIds = $quizzes->pluck('id');
        $attempts = GoshenQuizAttempt::query()->whereIn('quiz_id', $quizIds)->get();
        $winners = GoshenQuizWinner::query()->whereIn('quiz_id', $quizIds)->get();

        return response()->json([
            'status' => 'ok',
            'message' => 'Quiz management summary loaded.',
            'data' => [
                'totals' => [
                    'quizzes' => $quizzes->count(),
                    'active_quizzes' => $quizzes->where('is_active', true)->count(),
                    'inactive_quizzes' => $quizzes->where('is_active', false)->count(),
                    'attempts' => $attempts->count(),
                    'submitted_attempts' => $attempts->where('status', GoshenQuizAttempt::STATUS_SUBMITTED)->count(),
                    'timed_out_attempts' => $attempts->where('status', GoshenQuizAttempt::STATUS_TIMED_OUT)->count(),
                    'winners' => $winners->count(),
                    'pending_wallet_prizes' => $winners->where('wallet_prize_status', GoshenQuizWinner::WALLET_PRIZE_PENDING)->count(),
                    'paid_wallet_prizes' => $winners->where('wallet_prize_status', GoshenQuizWinner::WALLET_PRIZE_PAID)->count(),
                ],
                'quizzes' => $quizzes
                    ->map(fn (GoshenQuiz $quiz): array => $this->managementQuizPayload($quiz))
                    ->values(),
            ],
        ]);
    }

    public function updateSettings(Request $request, GoshenQuiz $quiz): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (! $this->canManageQuizzes($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is not authorized to manage Goshen quizzes.',
            ], 403);
        }

        $data = $this->payload($request);
        $validator = Validator::make($data, [
            'is_active' => ['sometimes', 'boolean'],
            'auto_select_winners' => ['sometimes', 'boolean'],
            'show_winners_immediately' => ['sometimes', 'boolean'],
            'winners_count' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'show_prize' => ['sometimes', 'boolean'],
            'prize_label' => ['nullable', 'string', 'max:120'],
            'wallet_prize_enabled' => ['sometimes', 'boolean'],
            'wallet_prize_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'wallet_prize_currency' => ['nullable', 'string', 'size:3'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after_or_equal:opens_at'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $fill = [];
        foreach ([
            'is_active',
            'auto_select_winners',
            'show_winners_immediately',
            'winners_count',
            'show_prize',
            'prize_label',
            'wallet_prize_enabled',
            'wallet_prize_amount',
            'opens_at',
            'closes_at',
        ] as $key) {
            if (array_key_exists($key, $validated)) {
                $fill[$key] = $validated[$key];
            }
        }

        if (array_key_exists('wallet_prize_currency', $validated) && filled($validated['wallet_prize_currency'])) {
            $fill['wallet_prize_currency'] = strtoupper((string) $validated['wallet_prize_currency']);
        }

        $quiz->forceFill($fill)->save();

        if (array_key_exists('winners_count', $fill) || array_key_exists('auto_select_winners', $fill)) {
            $this->quizzes->syncWinners($quiz);
        }

        $quiz = $quiz->fresh(['event', 'questions', 'winners.mobileUser'])
            ->loadCount([
                'questions',
                'attempts',
                'winners as selected_winners_count',
                'attempts as submitted_attempts_count' => fn ($query) => $query->where('status', GoshenQuizAttempt::STATUS_SUBMITTED),
                'attempts as timed_out_attempts_count' => fn ($query) => $query->where('status', GoshenQuizAttempt::STATUS_TIMED_OUT),
            ]);

        return response()->json([
            'status' => 'ok',
            'message' => 'Quiz settings updated.',
            'quiz' => $this->managementQuizPayload($quiz),
        ]);
    }

    public function payWinnerPrize(Request $request, GoshenQuiz $quiz, string $winner): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (! $this->canManageQuizzes($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is not authorized to fund quiz winner wallet prizes.',
            ], 403);
        }

        $winner = $quiz->winners()->whereKey($winner)->firstOrFail();

        try {
            $winner = $this->quizzes->payWalletPrize($winner, $user);
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Quiz prize wallet transfer completed.',
            'winner' => $this->quizzes->winnerPayload($winner),
        ]);
    }

    private function requireUser(Request $request): MobileUser|JsonResponse
    {
        $user = $this->mobileUserFromRequest($request);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in to use quiz.',
            ], 401);
        }

        if (! $user->canUseCommunity()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please verify your email address before using quiz.',
            ], 403);
        }

        return $user;
    }

    private function mobileUserFromRequest(Request $request): ?MobileUser
    {
        $data = $this->payload($request);
        $token = $request->bearerToken() ?: ($data['api_token'] ?? $request->input('api_token'));

        if (! is_string($token) || $token === '') {
            return null;
        }

        $user = MobileUser::query()->where('api_token_hash', hash('sha256', $token))->first();
        $user?->markApiSeen();

        return $user;
    }

    private function payload(Request $request): array
    {
        $payload = $request->input('data', $request->all());

        return is_array($payload) ? $payload : [];
    }

    private function canManageQuizPrizes(MobileUser $user): bool
    {
        return $this->canManageQuizzes($user);
    }

    private function canManageQuizzes(MobileUser $user): bool
    {
        if (! $user->canUseCommunity()) {
            return false;
        }

        if ($user->can('manage_goshen_quiz') || $user->can('manage_goshen_quizzes')) {
            return true;
        }

        return $user->roles()
            ->pluck('name')
            ->contains(fn ($role): bool => in_array(
                str($role)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                [
                    'admin',
                    'superadmin',
                    'eventmanager',
                    'quizmanager',
                    'goshenquizmanager',
                ],
                true,
            ));
    }

    private function managementQuizPayload(GoshenQuiz $quiz): array
    {
        return [
            'id' => $quiz->id,
            'title' => $quiz->title,
            'event_name' => $quiz->event?->name,
            'is_active' => (bool) $quiz->is_active,
            'audience' => $quiz->audience,
            'questions_count' => (int) ($quiz->questions_count ?? $quiz->questions->count()),
            'attempts_count' => (int) ($quiz->attempts_count ?? 0),
            'submitted_attempts_count' => (int) ($quiz->submitted_attempts_count ?? 0),
            'timed_out_attempts_count' => (int) ($quiz->timed_out_attempts_count ?? 0),
            'winners_count' => (int) $quiz->winners_count,
            'selected_winners_count' => (int) ($quiz->selected_winners_count ?? $quiz->winners->count()),
            'auto_select_winners' => (bool) $quiz->auto_select_winners,
            'show_winners_immediately' => (bool) $quiz->show_winners_immediately,
            'wallet_prize_enabled' => (bool) $quiz->wallet_prize_enabled,
            'wallet_prize_amount' => $quiz->wallet_prize_amount !== null ? (float) $quiz->wallet_prize_amount : null,
            'wallet_prize_currency' => $quiz->wallet_prize_currency,
            'opens_at' => $quiz->opens_at?->toIso8601String(),
            'closes_at' => $quiz->closes_at?->toIso8601String(),
            'winners' => $quiz->winners
                ->map(fn (GoshenQuizWinner $winner): array => $this->quizzes->winnerPayload($winner))
                ->values(),
        ];
    }
}
