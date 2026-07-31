<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Services;

use ChurchTools\ChurchBirthdayCelebrations\Support\BirthdayApiError;

class BirthdayGreetingGuard
{
    public function inspect(string $value, int $maximum): array
    {
        if (! mb_check_encoding($value, 'UTF-8')
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)
            || preg_match('/[\p{Cf}\p{Cs}\p{Co}\p{Cn}]/u', $value)) {
            BirthdayApiError::abort('INVALID_CONTENT', 'The message contains unsupported characters.', 422);
        }

        $body = trim(strip_tags($value));
        if ($body === '' || mb_strlen($body) > $maximum) {
            BirthdayApiError::abort('INVALID_CONTENT', 'The message is empty or too long.', 422);
        }

        $held = preg_match('/(?:https?:\/\/|www\.)\S+/iu', $body)
            || preg_match('/(.)\1{7,}/u', $body)
            || preg_match('/\b(\p{L}{2,})\b(?:[\s,.!?-]+\1\b){4,}/iu', $body);

        return ['body' => $body, 'status' => $held ? 'held' : 'visible'];
    }
}
