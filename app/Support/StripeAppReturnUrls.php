<?php

namespace App\Support;

class StripeAppReturnUrls
{
    public const SESSION_PLACEHOLDER = '{CHECKOUT_SESSION_ID}';

    public static function requested(array $payload): bool
    {
        return filter_var($payload['return_to_app'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array{success_url: string, cancel_url: string}
     */
    public static function wallet(): array
    {
        return self::pair('goshen-wallet');
    }

    /**
     * @return array{success_url: string, cancel_url: string}
     */
    public static function retreat(): array
    {
        return self::pair('goshen-payment', ['flow' => 'retreat']);
    }

    /**
     * @return array{success_url: string, cancel_url: string}
     */
    public static function giving(): array
    {
        return self::pair('goshen-payment', ['flow' => 'giving']);
    }

    /**
     * @param  array<string, string>  $query
     * @return array{success_url: string, cancel_url: string}
     */
    private static function pair(string $host, array $query = []): array
    {
        return [
            'success_url' => self::url($host, 'success', $query + [
                'session_id' => self::SESSION_PLACEHOLDER,
            ]),
            'cancel_url' => self::url($host, 'cancelled', $query),
        ];
    }

    /**
     * @param  array<string, string>  $query
     */
    private static function url(string $host, string $status, array $query): string
    {
        $url = "triumphant://{$host}/{$status}";

        if ($query === []) {
            return $url;
        }

        $parts = [];
        foreach ($query as $key => $value) {
            $encodedValue = $value === self::SESSION_PLACEHOLDER
                ? self::SESSION_PLACEHOLDER
                : rawurlencode($value);
            $parts[] = rawurlencode($key).'='.$encodedValue;
        }

        return $url.'?'.implode('&', $parts);
    }
}
