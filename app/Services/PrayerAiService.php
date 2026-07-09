<?php

namespace App\Services;

use App\Models\AiProviderSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PrayerAiService
{
    public function rewrite(string $text, string $kind): string
    {
        $fallback = trim(strip_tags($text));

        return $this->ask(
            "Rewrite this {$kind} with warmth, clarity, respect, and gentle prayerfulness. Preserve the meaning. Do not promise guaranteed miracles or give medical, legal, or financial certainty. Return JSON only: {\"text\":\"...\"}.",
            $fallback,
            ['text' => $fallback]
        )['text'] ?? $fallback;
    }

    public function suggestions(string $text): array
    {
        $fallback = [
            'I pray for you',
            'May God strengthen you',
            'Peace of God be with you',
        ];

        $result = $this->ask(
            'Generate 3 to 5 short, respectful Christian prayer response button labels for this prayer request. Avoid guaranteed miracle claims and avoid harmful, abusive, manipulative, discriminatory, or exploitative wording. Return JSON only: {"suggestions":["..."]}.',
            trim(strip_tags($text)),
            ['suggestions' => $fallback]
        );

        return collect($result['suggestions'] ?? $fallback)
            ->filter(fn ($item) => is_string($item) && filled($item))
            ->map(fn ($item) => Str::of($item)->squish()->limit(64, '')->toString())
            ->unique()
            ->values()
            ->take(5)
            ->all();
    }

    public function explainVerse(string $reference, string $verseText): array
    {
        return $this->ask(
            'You are a thoughtful, scholarly yet accessible Bible teacher. Provide a theological explanation of the given Bible verse. Include historical context, original language insights (Hebrew/Greek), and practical application for daily life. Be respectful of Christian tradition. Return JSON only: {"explanation": "...detailed explanation..."}.',
            "{$reference}: {$verseText}",
            ['explanation' => 'Unable to generate explanation at this time.']
        );
    }

    public function searchBibleByTopic(string $topic): array
    {
        return $this->ask(
            'You are a knowledgeable Bible scholar. Find 5 to 8 relevant Bible verses related to the given topic or question. Use the King James Version (KJV) text. For each verse include the reference (book chapter:verse), the verse text, and a brief note on its relevance to the topic. Return JSON only: {"results": [{"reference": "...", "text": "...", "relevance": "..."}]}.',
            $topic,
            ['results' => []]
        );
    }

    public function draftMinistryMessage(string $purpose, bool $includeVerse = false, string $channel = 'inbox'): array
    {
        $purpose = trim(strip_tags($purpose));
        if ($purpose === '') {
            return [
                'subject' => 'A warm message from MFM Triumphant Church',
                'body' => 'May the peace of God be with you. We are grateful to have you as part of this church family.',
            ];
        }

        $verseInstruction = $includeVerse
            ? 'Add one relevant Bible verse reference and a short quoted verse line when it naturally supports the message.'
            : 'Do not add a Bible verse unless it is essential.';

        $result = $this->ask(
            "Draft a warm, appreciative, prayerful Christian ministry {$channel} message for a church app admin. Use a gentle MFM Triumphant Church tone. {$verseInstruction} Do not claim guaranteed miracles. Do not auto-send. Return JSON only: {\"subject\":\"...\",\"body\":\"...\"}.",
            $purpose,
            [
                'subject' => Str::of($purpose)->squish()->limit(80, '')->toString(),
                'body' => $purpose,
            ]
        );

        return [
            'subject' => Str::of((string) ($result['subject'] ?? $purpose))->squish()->limit(160, '')->toString(),
            'body' => trim((string) ($result['body'] ?? $purpose)),
        ];
    }

    private function ask(string $system, string $input, array $fallback): array
    {
        $setting = AiProviderSetting::active();
        $provider = $setting?->provider ?: config('services.ai.provider', 'openai');
        $key = $setting?->api_key ?: config("services.{$provider}.key");
        if (! $key) {
            return $fallback;
        }
        $model = $setting?->model ?: config("services.{$provider}.model");
        $timeout = $setting?->timeout_seconds ?: 20;

        try {
            $response = match ($provider) {
                'gemini' => Http::withHeaders(['x-goog-api-key' => $key])
                    ->acceptJson()
                    ->timeout($timeout)
                    ->post($setting?->base_url ?: "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                        'contents' => [[
                            'parts' => [['text' => $system."\n\nInput:\n".$input]],
                        ]],
                        'generationConfig' => [
                            'responseMimeType' => 'application/json',
                        ],
                    ]),
                'deepseek' => Http::withToken($key)
                    ->acceptJson()
                    ->timeout($timeout)
                    ->post($setting?->base_url ?: 'https://api.deepseek.com/chat/completions', [
                        'model' => $model ?: 'deepseek-v4-flash',
                        'messages' => [
                            ['role' => 'system', 'content' => $system.' Return valid JSON.'],
                            ['role' => 'user', 'content' => $input],
                        ],
                        'response_format' => ['type' => 'json_object'],
                    ]),
                default => Http::withToken($key)
                    ->acceptJson()
                    ->timeout($timeout)
                    ->post($setting?->base_url ?: 'https://api.openai.com/v1/responses', [
                        'model' => $model ?: 'gpt-5.4-mini',
                        'input' => [
                            ['role' => 'system', 'content' => $system],
                            ['role' => 'user', 'content' => $input],
                        ],
                        'text' => ['format' => ['type' => 'json_object']],
                    ]),
            };

            if (! $response->successful()) {
                return $fallback;
            }

            $content = match ($provider) {
                'gemini' => $response->json('candidates.0.content.parts.0.text'),
                'deepseek' => $response->json('choices.0.message.content'),
                default => $response->json('output.0.content.0.text'),
            };
            if (! is_string($content)) {
                return $fallback;
            }

            $decoded = json_decode($content, true);

            return is_array($decoded) ? $decoded : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
