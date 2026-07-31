<?php

namespace ChurchTools\ChurchBirthdayCelebrations\Support;

use Illuminate\Http\Exceptions\HttpResponseException;

class BirthdayApiError
{
    public static function abort(string $code, string $message, int $status): never
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'code' => $code,
            'message' => $message,
        ], $status));
    }
}
