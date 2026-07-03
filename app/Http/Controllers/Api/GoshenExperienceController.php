<?php

namespace App\Http\Controllers\Api;

use App\Models\GoshenExperienceReminder;
use App\Models\GoshenExperienceQuestion;
use App\Models\GoshenExperienceResponse;
use App\Models\GoshenExperienceSurvey;
use App\Models\MobileUser;
use App\Services\GoshenExperienceEligibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Personal\EventInstallments\Models\Event;

class GoshenExperienceController extends Controller
{
    public function __construct(private readonly GoshenExperienceEligibility $eligibility) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->mobileUserFromRequest($request);

        $surveys = GoshenExperienceSurvey::query()
            ->with(['event.schedules', 'questions', 'responses' => fn ($query) => $user ? $query->where('mobile_user_id', $user->id) : $query->whereRaw('1 = 0')])
            ->open()
            ->whereHas('event', fn ($query) => $this->applyGoshenEventScope($query))
            ->latest('id')
            ->get()
            ->map(fn (GoshenExperienceSurvey $survey): array => $this->surveyPayload($survey, $user))
            ->values();

        return response()->json([
            'status' => 'ok',
            'message' => 'Goshen Experience surveys loaded.',
            'data' => $surveys,
        ]);
    }

    public function show(Request $request, GoshenExperienceSurvey $survey): JsonResponse
    {
        abort_unless($this->surveyIsOpen($survey) && $this->isGoshenEvent($survey->event), 404);

        $survey->loadMissing(['event.schedules', 'questions']);

        return response()->json([
            'status' => 'ok',
            'data' => $this->surveyPayload($survey, $this->mobileUserFromRequest($request)),
        ]);
    }

    public function store(Request $request, GoshenExperienceSurvey $survey): JsonResponse
    {
        abort_unless($this->surveyIsOpen($survey) && $this->isGoshenEvent($survey->event), 404);

        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $survey->loadMissing(['event', 'questions']);
        $ticket = $survey->allow_all_authenticated_users
            ? null
            : $this->eligibility->eligibleTicketFor($user, $survey->event);
        if (! $survey->allow_all_authenticated_users && ! $ticket) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only checked-in attendees for this Goshen Retreat can share this experience. We would love to see you at the next retreat.',
            ], 403);
        }

        if ($survey->responses()->where('mobile_user_id', $user->id)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Thank you. You have already submitted this Goshen Experience response.',
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'story' => ['nullable', 'string', 'max:5000'],
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable'],
            'audio' => [
                'nullable',
                'file',
                'extensions:mp3,m4a,aac,wav,ogg,webm',
                'max:32768',
            ],
            'audio_duration_seconds' => ['nullable', 'integer', 'min:1', 'max:300'],
            'video' => [
                'nullable',
                'file',
                'extensions:mp4,webm,mov,qt',
                'max:204800',
            ],
            'video_duration_seconds' => ['nullable', 'integer', 'min:1', 'max:600'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        if (! $survey->allow_audio && $request->hasFile('audio')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Audio responses are not enabled for this survey.',
            ], 422);
        }

        if (! $survey->allow_video && $request->hasFile('video')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Video responses are not enabled for this survey.',
            ], 422);
        }

        $answers = $this->normalizedAnswers($survey, $validated['answers'] ?? []);
        if ($answers instanceof JsonResponse) {
            return $answers;
        }

        if (blank($validated['story'] ?? null) && empty($answers) && ! $request->hasFile('audio') && ! $request->hasFile('video')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please share a written, audio, video, or survey response before submitting.',
            ], 422);
        }

        $audioPath = $request->hasFile('audio')
            ? $request->file('audio')->store('goshen/experience/audio', 'public')
            : null;
        $videoPath = $request->hasFile('video')
            ? $request->file('video')->store('goshen/experience/video', 'public')
            : null;

        $response = GoshenExperienceResponse::query()->create([
            'survey_id' => $survey->id,
            'event_id' => $survey->event_id,
            'mobile_user_id' => $user->id,
            'booking_id' => $ticket?->booking_id,
            'ticket_id' => $ticket?->id,
            'story' => trim((string) ($validated['story'] ?? '')) ?: null,
            'audio_path' => $audioPath,
            'audio_duration_seconds' => $validated['audio_duration_seconds'] ?? null,
            'video_path' => $videoPath,
            'video_duration_seconds' => $validated['video_duration_seconds'] ?? null,
            'answers' => $answers,
            'submitted_at' => now(),
        ]);

        GoshenExperienceReminder::query()
            ->where('survey_id', $survey->id)
            ->where('mobile_user_id', $user->id)
            ->update(['completed_at' => now()]);

        return response()->json([
            'status' => 'ok',
            'message' => $survey->thank_you_message ?: 'Thank you for sharing your Goshen Experience. May the Lord perfect every testimony from this retreat in your life.',
            'data' => $this->responsePayload($response->load(['survey', 'event'])),
        ], 201);
    }

    public function stats(Request $request, string $event): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $event = $this->eventFromKey($event);
        if (! $event || ! $this->isGoshenEvent($event)) {
            return response()->json([
                'status' => 'error',
                'message' => 'The selected Goshen Retreat edition could not be found.',
            ], 404);
        }

        if (! $this->canViewStats($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is not authorized to view Goshen Experience stats.',
            ], 403);
        }

        $checkedInCount = $this->eligibility->checkedInMobileUsersFor($event)->count();
        $responses = GoshenExperienceResponse::query()
            ->with(['mobileUser', 'survey'])
            ->where('event_id', $event->id)
            ->latest('submitted_at')
            ->latest()
            ->get();
        $surveys = GoshenExperienceSurvey::query()
            ->with('questions')
            ->where('event_id', $event->id)
            ->latest()
            ->get();
        $questions = $surveys
            ->flatMap(fn (GoshenExperienceSurvey $survey) => $survey->questions)
            ->values();

        $byGender = $responses
            ->groupBy(fn (GoshenExperienceResponse $response): string => ucfirst((string) ($response->mobileUser?->gender ?: 'Unknown')))
            ->map->count()
            ->all();

        $byCountry = $responses
            ->groupBy(fn (GoshenExperienceResponse $response): string => (string) ($response->mobileUser?->country_of_residence ?: 'Unknown'))
            ->map->count()
            ->all();

        $byState = $responses
            ->groupBy(fn (GoshenExperienceResponse $response): string => (string) ($response->mobileUser?->state_county_province ?: 'Unknown'))
            ->map->count()
            ->all();

        $byAgeGroup = $responses
            ->groupBy(fn (GoshenExperienceResponse $response): string => $this->ageGroup($response->mobileUser?->date_of_birth))
            ->map->count()
            ->all();

        return response()->json([
            'status' => 'ok',
            'data' => [
                'event' => [
                    'id' => $event->id,
                    'public_id' => $event->public_id,
                    'name' => $event->name,
                ],
                'checked_in_attendees' => $checkedInCount,
                'responses' => $responses->count(),
                'response_rate' => $checkedInCount > 0 ? round(($responses->count() / $checkedInCount) * 100, 1) : 0,
                'by_gender' => $byGender,
                'by_country' => $byCountry,
                'by_state' => $byState,
                'by_age_group' => $byAgeGroup,
                'surveys' => $surveys
                    ->map(fn (GoshenExperienceSurvey $survey): array => $this->surveyManagementPayload(
                        $survey,
                        $responses->where('survey_id', $survey->id)->count(),
                    ))
                    ->values()
                    ->all(),
                'question_stats' => $questions
                    ->map(fn (GoshenExperienceQuestion $question): array => $this->surveyQuestionStatsPayload($question, $responses))
                    ->values()
                    ->all(),
                'recent_responses' => $responses
                    ->take(25)
                    ->map(fn (GoshenExperienceResponse $response): array => $this->surveyResponseReviewPayload($response))
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function updateSurveySettings(Request $request, string $survey): JsonResponse
    {
        $user = $this->requireUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $survey = GoshenExperienceSurvey::query()
            ->with(['event', 'questions'])
            ->whereKey($survey)
            ->first();

        if (! $survey) {
            return response()->json([
                'status' => 'error',
                'message' => 'The selected Goshen Experience survey could not be found.',
            ], 404);
        }

        $survey->loadMissing(['event', 'questions']);
        if (! $this->isGoshenEvent($survey->event)) {
            return response()->json([
                'status' => 'error',
                'message' => 'The selected Goshen Experience survey could not be found.',
            ], 404);
        }

        if (! $this->canViewStats($user)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is not authorized to manage Goshen Experience surveys.',
            ], 403);
        }

        $data = $this->payload($request);
        $validator = Validator::make($data, [
            'is_active' => ['sometimes', 'boolean'],
            'allow_audio' => ['sometimes', 'boolean'],
            'allow_video' => ['sometimes', 'boolean'],
            'allow_all_authenticated_users' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $updates = collect([
            'is_active',
            'allow_audio',
            'allow_video',
            'allow_all_authenticated_users',
        ])
            ->filter(fn (string $key): bool => array_key_exists($key, $validated))
            ->mapWithKeys(fn (string $key): array => [$key => filter_var($validated[$key], FILTER_VALIDATE_BOOLEAN)])
            ->all();

        if ($updates === []) {
            return response()->json([
                'status' => 'error',
                'message' => 'Choose at least one survey setting to update.',
            ], 422);
        }

        $survey->forceFill($updates)->save();
        $survey->refresh()->loadMissing('questions');

        return response()->json([
            'status' => 'ok',
            'message' => 'Survey settings have been updated.',
            'survey' => $this->surveyManagementPayload($survey),
        ]);
    }

    private function surveyPayload(GoshenExperienceSurvey $survey, ?MobileUser $user = null): array
    {
        $response = $user ? $survey->responses->firstWhere('mobile_user_id', $user->id) : null;
        $eligible = $user ? $this->surveyEligibleForUser($survey, $user) : false;

        return [
            'id' => $survey->id,
            'title' => $survey->title,
            'description' => $survey->description,
            'is_active' => $survey->is_active,
            'allow_audio' => $survey->allow_audio,
            'allow_video' => $survey->allow_video,
            'allow_all_authenticated_users' => $survey->allow_all_authenticated_users,
            'opens_at' => $survey->opens_at?->toIso8601String(),
            'closes_at' => $survey->closes_at?->toIso8601String(),
            'already_submitted' => (bool) $response,
            'eligible_to_submit' => $eligible,
            'event' => [
                'id' => $survey->event?->id,
                'public_id' => $survey->event?->public_id,
                'name' => $survey->event?->name,
            ],
            'questions' => $survey->questions
                ->map(fn ($question): array => [
                    'id' => $question->id,
                    'prompt' => $question->prompt,
                    'type' => $question->type,
                    'options' => $this->questionOptions($question),
                    'settings' => $question->settings ?: (object) [],
                    'conditional_logic' => $question->conditional_logic ?: (object) [],
                    'is_required' => $question->is_required,
                    'sort_order' => $question->sort_order,
                ])
                ->values()
                ->all(),
            'my_response' => $response ? $this->responsePayload($response) : null,
        ];
    }

    private function surveyManagementPayload(GoshenExperienceSurvey $survey, ?int $responsesCount = null): array
    {
        $survey->loadMissing('questions');

        return [
            'id' => $survey->id,
            'title' => $survey->title,
            'is_active' => (bool) $survey->is_active,
            'allow_audio' => (bool) $survey->allow_audio,
            'allow_video' => (bool) $survey->allow_video,
            'allow_all_authenticated_users' => (bool) $survey->allow_all_authenticated_users,
            'opens_at' => $survey->opens_at?->toIso8601String(),
            'closes_at' => $survey->closes_at?->toIso8601String(),
            'questions_count' => $survey->questions->count(),
            'responses_count' => $responsesCount ?? $survey->responses()->count(),
        ];
    }

    private function eventFromKey(string $key): ?Event
    {
        $key = trim($key);
        if ($key === '') {
            return null;
        }

        return Event::query()
            ->when(
                ctype_digit($key),
                fn ($query) => $query->where('id', (int) $key)->orWhere('public_id', $key),
                fn ($query) => $query->where('public_id', $key),
            )
            ->first();
    }

    private function responsePayload(GoshenExperienceResponse $response): array
    {
        return [
            'id' => $response->id,
            'story' => $response->story,
            'answers' => $response->answers ?: (object) [],
            'audio_url' => $response->audio_path ? $this->publicStorageUrl($response->audio_path) : null,
            'audio_duration_seconds' => $response->audio_duration_seconds,
            'video_url' => $response->video_path ? $this->publicStorageUrl($response->video_path) : null,
            'video_duration_seconds' => $response->video_duration_seconds,
            'submitted_at' => $response->submitted_at?->toIso8601String(),
        ];
    }

    private function surveyQuestionStatsPayload(GoshenExperienceQuestion $question, $responses): array
    {
        $answered = collect($responses)
            ->map(fn (GoshenExperienceResponse $response): ?array => $this->answerEntryForQuestion($response, $question))
            ->filter()
            ->values();
        $breakdown = [];
        $samples = [];

        foreach ($answered as $entry) {
            $answer = $entry['answer'] ?? null;
            $items = $this->answerLabelItems($question, $answer);
            foreach ($items as $item) {
                $key = (string) ($item['value'] ?? $item['label'] ?? '');
                if ($key === '') {
                    continue;
                }

                $breakdown[$key] ??= [
                    'key' => $key,
                    'label' => (string) ($item['label'] ?? $key),
                    'count' => 0,
                ];

                if (! empty($item['image_url'])) {
                    $breakdown[$key]['image_url'] = $item['image_url'];
                }

                if (! empty($item['color_hex'])) {
                    $breakdown[$key]['color_hex'] = $item['color_hex'];
                }

                $breakdown[$key]['count']++;
            }

            $text = $this->answerSummaryText($answer, $question);
            if ($text !== '' && count($samples) < 5) {
                $samples[] = $text;
            }
        }

        $answeredCount = max(1, $answered->count());
        $breakdownRows = collect($breakdown)
            ->map(function (array $row) use ($answeredCount): array {
                $row['percentage'] = round(((int) $row['count'] / $answeredCount) * 100, 1);

                return $row;
            })
            ->sortByDesc('count')
            ->values()
            ->all();

        return [
            'question_id' => $question->id,
            'survey_id' => $question->survey_id,
            'prompt' => $question->prompt,
            'type' => $question->type,
            'is_required' => (bool) $question->is_required,
            'responses' => $answered->count(),
            'breakdown' => $breakdownRows,
            'samples' => $samples,
        ];
    }

    private function surveyResponseReviewPayload(GoshenExperienceResponse $response): array
    {
        $answers = collect(is_array($response->answers) ? $response->answers : [])
            ->map(function (mixed $entry): ?array {
                if (! is_array($entry)) {
                    return null;
                }

                $prompt = trim((string) ($entry['prompt'] ?? 'Question'));
                $answer = $entry['answer'] ?? null;
                $type = trim((string) ($entry['type'] ?? 'text'));
                $summary = $this->answerSummaryText($answer, null);

                if ($summary === '') {
                    return null;
                }

                return [
                    'question_id' => $entry['question_id'] ?? null,
                    'prompt' => $prompt !== '' ? $prompt : 'Question',
                    'type' => $type !== '' ? $type : 'text',
                    'answer' => $summary,
                ];
            })
            ->filter()
            ->take(10)
            ->values()
            ->all();

        return [
            'id' => $response->id,
            'survey_id' => $response->survey_id,
            'survey_title' => $response->survey?->title ?: 'Goshen Experience',
            'member_name' => $response->mobileUser?->name ?: 'Member',
            'member_email' => $response->mobileUser?->email,
            'story' => $response->story,
            'answers' => $answers,
            'audio_url' => $response->audio_path ? $this->publicStorageUrl($response->audio_path) : null,
            'audio_duration_seconds' => $response->audio_duration_seconds,
            'video_url' => $response->video_path ? $this->publicStorageUrl($response->video_path) : null,
            'video_duration_seconds' => $response->video_duration_seconds,
            'submitted_at' => $response->submitted_at?->toIso8601String(),
        ];
    }

    private function answerEntryForQuestion(GoshenExperienceResponse $response, GoshenExperienceQuestion $question): ?array
    {
        $answers = is_array($response->answers) ? $response->answers : [];
        $entry = $answers[(string) $question->id] ?? $answers[$question->id] ?? null;

        if (! is_array($entry)) {
            $entry = collect($answers)->first(function (mixed $answer) use ($question): bool {
                return is_array($answer) && (int) ($answer['question_id'] ?? 0) === (int) $question->id;
            });
        }

        if (! is_array($entry) || blank($entry['answer'] ?? null)) {
            return null;
        }

        return $entry;
    }

    private function answerLabelItems(?GoshenExperienceQuestion $question, mixed $answer): array
    {
        if (blank($answer)) {
            return [];
        }

        if (is_array($answer) && array_key_exists('rating', $answer)) {
            $rating = (int) $answer['rating'];
            $settings = $question && is_array($question->settings) ? $question->settings : [];
            $max = max(1, (int) ($answer['max'] ?? $settings['rating_max'] ?? 5));

            return [[
                'value' => (string) $rating,
                'label' => "{$rating}/{$max}",
            ]];
        }

        if (is_array($answer) && (array_key_exists('label', $answer) || array_key_exists('value', $answer))) {
            $label = trim((string) ($answer['label'] ?? $answer['value'] ?? ''));
            $value = trim((string) ($answer['value'] ?? $label));

            return [[
                'value' => $value !== '' ? $value : $label,
                'label' => $label !== '' ? $label : $value,
                'image_url' => $answer['image_url'] ?? null,
                'color_hex' => $answer['color_hex'] ?? null,
            ]];
        }

        if (is_array($answer)) {
            return collect($answer)
                ->flatMap(fn (mixed $value): array => $this->answerLabelItems($question, $value))
                ->values()
                ->all();
        }

        $value = trim((string) $answer);
        if ($value === '') {
            return [];
        }

        return [$this->questionOptionDisplay($question, $value)];
    }

    private function questionOptionDisplay(?GoshenExperienceQuestion $question, string $value): array
    {
        if ($question) {
            $option = collect($this->questionOptions($question))
                ->first(fn ($option): bool => $this->optionValue($option) === $value);

            if ($option !== null) {
                $row = [
                    'value' => $this->optionValue($option),
                    'label' => $this->optionLabel($option),
                ];

                if (is_array($option) && ! empty($option['image_url'])) {
                    $row['image_url'] = $option['image_url'];
                }

                if (is_array($option) && ! empty($option['color_hex'])) {
                    $row['color_hex'] = $option['color_hex'];
                }

                return $row;
            }
        }

        return [
            'value' => $value,
            'label' => $value,
        ];
    }

    private function answerSummaryText(mixed $answer, ?GoshenExperienceQuestion $question): string
    {
        if (blank($answer)) {
            return '';
        }

        if (is_array($answer) && array_key_exists('rating', $answer)) {
            $items = $this->answerLabelItems($question, $answer);
            $label = (string) ($items[0]['label'] ?? $answer['rating']);
            $reason = trim((string) ($answer['reason'] ?? ''));

            return $reason !== '' ? "{$label}: {$reason}" : $label;
        }

        $labels = collect($this->answerLabelItems($question, $answer))
            ->pluck('label')
            ->filter()
            ->values()
            ->all();

        if ($labels !== []) {
            return implode(', ', $labels);
        }

        return is_array($answer) ? '' : trim((string) $answer);
    }

    private function questionOptions(GoshenExperienceQuestion $question): array
    {
        $settings = is_array($question->settings) ? $question->settings : [];

        if ($question->type === GoshenExperienceQuestion::TYPE_IMAGE_CHOICE) {
            return collect($settings['image_options'] ?? [])
                ->map(function (mixed $option): ?array {
                    if (! is_array($option)) {
                        return null;
                    }

                    $label = trim((string) ($option['label'] ?? ''));
                    $imagePath = $this->storedMediaPath($option['image_path'] ?? null);
                    if ($label === '' || $imagePath === null) {
                        return null;
                    }

                    return [
                        'label' => $label,
                        'value' => $this->optionValue($option),
                        'image_path' => $imagePath,
                        'image_url' => $this->publicStorageUrl($imagePath),
                    ];
                })
                ->filter()
                ->values()
                ->all();
        }

        if ($question->type === GoshenExperienceQuestion::TYPE_COLOR_CHOICE) {
            return collect($settings['color_options'] ?? [])
                ->map(function (mixed $option): ?array {
                    if (! is_array($option)) {
                        return null;
                    }

                    $label = trim((string) ($option['label'] ?? ''));
                    if ($label === '') {
                        return null;
                    }

                    return [
                        'label' => $label,
                        'value' => $this->optionValue($option),
                        'color_hex' => $this->normalizeColorHex($option['color_hex'] ?? null),
                    ];
                })
                ->filter()
                ->values()
                ->all();
        }

        return $question->options ?: [];
    }

    private function selectedOptionAnswer(GoshenExperienceQuestion $question, string $value): array
    {
        $option = collect($this->questionOptions($question))
            ->first(fn ($option): bool => $this->optionValue($option) === $value);

        $answer = [
            'value' => $value,
            'label' => $this->optionLabel($option),
        ];

        if ($question->type === GoshenExperienceQuestion::TYPE_IMAGE_CHOICE && is_array($option)) {
            $imagePath = $this->storedMediaPath($option['image_path'] ?? null);
            if ($imagePath !== null) {
                $answer['image_path'] = $imagePath;
                $answer['image_url'] = $this->publicStorageUrl($imagePath);
            }
        }

        if ($question->type === GoshenExperienceQuestion::TYPE_COLOR_CHOICE && is_array($option)) {
            $answer['color_hex'] = $this->normalizeColorHex($option['color_hex'] ?? null);
        }

        return $answer;
    }

    private function choiceQuestionTypes(): array
    {
        return [
            GoshenExperienceQuestion::TYPE_CHOICE,
            GoshenExperienceQuestion::TYPE_MULTI_CHOICE,
            GoshenExperienceQuestion::TYPE_IMAGE_CHOICE,
            GoshenExperienceQuestion::TYPE_COLOR_CHOICE,
        ];
    }

    private function optionValue(mixed $option): string
    {
        if (is_array($option)) {
            $value = trim((string) ($option['value'] ?? ''));
            if ($value !== '') {
                return $value;
            }

            return trim((string) ($option['label'] ?? ''));
        }

        return trim((string) $option);
    }

    private function optionLabel(mixed $option): string
    {
        if (is_array($option)) {
            $label = trim((string) ($option['label'] ?? ''));
            if ($label !== '') {
                return $label;
            }

            return $this->optionValue($option);
        }

        return trim((string) $option);
    }

    private function storedMediaPath(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = reset($path);
        }

        $path = trim((string) $path);

        return $path === '' ? null : $path;
    }

    private function normalizeColorHex(mixed $color): string
    {
        $color = trim((string) $color);
        if (preg_match('/^#?[0-9a-fA-F]{6}$/', $color) === 1) {
            return '#' . strtolower(ltrim($color, '#'));
        }

        return '#ffffff';
    }

    private function publicStorageUrl(string $path): string
    {
        $url = Storage::disk('public')->url($path);

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : url($url);
    }

    private function normalizedAnswers(GoshenExperienceSurvey $survey, array $answers): array|JsonResponse
    {
        $normalized = [];
        foreach ($survey->questions as $question) {
            if (! $this->questionIsVisible($question, $answers)) {
                continue;
            }

            $key = (string) $question->id;
            $value = $this->decodedAnswerValue($answers[$key] ?? $answers[$question->id] ?? null);
            if ($question->is_required && blank($value)) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Please answer: {$question->prompt}",
                ], 422);
            }

            if (blank($value)) {
                continue;
            }

            if (in_array($question->type, $this->choiceQuestionTypes(), true)) {
                $options = $this->questionOptions($question);
                $allowed = collect($options)->map(fn ($option) => $this->optionValue($option))->filter()->values();
                $values = is_array($value) ? $value : [$value];
                $unknown = collect($values)->filter(fn ($item) => ! $allowed->contains($item));
                if ($unknown->isNotEmpty()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Please choose a valid option for: {$question->prompt}",
                    ], 422);
                }

                if (in_array($question->type, [
                    GoshenExperienceQuestion::TYPE_IMAGE_CHOICE,
                    GoshenExperienceQuestion::TYPE_COLOR_CHOICE,
                ], true)) {
                    if (is_array($value)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => "Please choose one option for: {$question->prompt}",
                        ], 422);
                    }

                    $value = $this->selectedOptionAnswer($question, (string) $value);
                }
            }

            if ($question->type === GoshenExperienceQuestion::TYPE_RATING) {
                $rating = is_array($value) ? ($value['rating'] ?? null) : $value;
                $reason = trim((string) (is_array($value) ? ($value['reason'] ?? '') : ''));
                $settings = is_array($question->settings) ? $question->settings : [];
                $max = max(1, min(10, (int) ($settings['rating_max'] ?? 5)));

                if (! is_numeric($rating) || (int) $rating < 1 || (int) $rating > $max) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Please choose a star rating from 1 to {$max} for: {$question->prompt}",
                    ], 422);
                }

                if (filter_var($settings['require_rating_reason'] ?? false, FILTER_VALIDATE_BOOLEAN) && $reason === '') {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Please add the reason for your rating: {$question->prompt}",
                    ], 422);
                }

                $value = [
                    'rating' => (int) $rating,
                    'reason' => $reason,
                    'max' => $max,
                ];
            }

            $normalized[$key] = [
                'question_id' => $question->id,
                'prompt' => $question->prompt,
                'type' => $question->type,
                'answer' => $value,
            ];
        }

        return $normalized;
    }

    private function surveyEligibleForUser(GoshenExperienceSurvey $survey, MobileUser $user): bool
    {
        if ($survey->allow_all_authenticated_users) {
            return true;
        }

        return (bool) $this->eligibility->eligibleTicketFor($user, $survey->event);
    }

    private function surveyIsOpen(GoshenExperienceSurvey $survey): bool
    {
        if (! $survey->is_active) {
            return false;
        }

        $now = now();

        if ($survey->opens_at && $survey->opens_at->isFuture()) {
            return false;
        }

        if ($survey->closes_at && $survey->closes_at->lt($now)) {
            return false;
        }

        return true;
    }

    private function questionIsVisible(GoshenExperienceQuestion $question, array $answers): bool
    {
        $logic = is_array($question->conditional_logic) ? $question->conditional_logic : [];
        if (! filter_var($logic['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $sourceQuestionId = (string) ($logic['question_id'] ?? '');
        if ($sourceQuestionId === '') {
            return true;
        }

        $sourceValue = $this->decodedAnswerValue($answers[$sourceQuestionId] ?? $answers[(int) $sourceQuestionId] ?? null);
        $operator = (string) ($logic['operator'] ?? 'equals');
        $expected = trim((string) ($logic['value'] ?? ''));
        $values = $this->answerComparisonValues($sourceValue);
        $answered = collect($values)->contains(fn (string $value): bool => trim($value) !== '');

        return match ($operator) {
            'answered' => $answered,
            'not_answered' => ! $answered,
            'not_equals' => ! collect($values)->contains(fn (string $value): bool => strcasecmp(trim($value), $expected) === 0),
            'contains' => collect($values)->contains(fn (string $value): bool => str_contains(strtolower($value), strtolower($expected))),
            'not_contains' => ! collect($values)->contains(fn (string $value): bool => str_contains(strtolower($value), strtolower($expected))),
            default => collect($values)->contains(fn (string $value): bool => strcasecmp(trim($value), $expected) === 0),
        };
    }

    private function decodedAnswerValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }

    private function answerComparisonValues(mixed $value): array
    {
        if (is_array($value)) {
            if (array_key_exists('rating', $value)) {
                return [(string) $value['rating']];
            }

            return collect($value)
                ->flatten()
                ->map(fn ($item): string => (string) $item)
                ->all();
        }

        return [(string) $value];
    }

    private function requireUser(Request $request): MobileUser|JsonResponse
    {
        $user = $this->mobileUserFromRequest($request);
        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please sign in to continue.',
            ], 401);
        }

        if (! $user->canUseCommunity()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please verify your email address before using Goshen Experience.',
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

    private function canViewStats(MobileUser $user): bool
    {
        if ($user->hasAnyRole(['super_admin', 'admin', 'event_manager', 'Event Manager'])) {
            return true;
        }

        return $user->roles()
            ->pluck('name')
            ->contains(fn ($role) => in_array(
                str($role)->lower()->replaceMatches('/[^a-z]/', '')->toString(),
                ['eventmanager', 'goshenmanager', 'retreatmanager'],
                true,
            ));
    }

    private function ageGroup(?string $dateOfBirth): string
    {
        if (blank($dateOfBirth)) {
            return 'Unknown';
        }

        try {
            $age = now()->diffInYears(\Carbon\Carbon::parse($dateOfBirth));
        } catch (\Throwable) {
            return 'Unknown';
        }

        return match (true) {
            $age < 18 => 'Under 18',
            $age <= 24 => '18-24',
            $age <= 34 => '25-34',
            $age <= 44 => '35-44',
            $age <= 54 => '45-54',
            default => '55+',
        };
    }

    private function applyGoshenEventScope($query): void
    {
        $query
            ->where('settings->module', 'goshen_retreat')
            ->orWhere('settings->module', 'goshen-retreat')
            ->orWhere('settings->app_module', 'goshen_retreat')
            ->orWhere('slug', 'like', 'goshen-retreat%')
            ->orWhere('slug', 'like', 'goshen-%')
            ->orWhere('name', 'like', '%Goshen Retreat%');
    }

    private function isGoshenEvent(?Event $event): bool
    {
        if (! $event) {
            return false;
        }

        $settings = is_array($event->settings) ? $event->settings : [];
        $module = strtolower(trim((string) ($settings['module'] ?? $settings['app_module'] ?? '')));
        if (in_array($module, ['goshen_retreat', 'goshen-retreat'], true)) {
            return true;
        }

        $slug = strtolower((string) $event->slug);
        if (str_starts_with($slug, 'goshen-retreat') || str_starts_with($slug, 'goshen-')) {
            return true;
        }

        return str_contains(strtolower((string) $event->name), 'goshen retreat');
    }
}
