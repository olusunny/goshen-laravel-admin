<?php

namespace App\Http\Controllers;

use App\Models\DynamicFormSubmission;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DynamicFormSubmissionFileController extends Controller
{
    public function show(DynamicFormSubmission $submission, string $field): StreamedResponse|Response
    {
        abort_unless(auth()->check(), 403);

        $answer = data_get($submission->answers, "{$field}.answer");
        abort_unless(is_array($answer), 404);

        $disk = (string) ($answer['disk'] ?? 'local');
        $path = (string) ($answer['file_path'] ?? '');
        abort_if($path === '', 404);
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download(
            $path,
            (string) ($answer['original_name'] ?? basename($path)),
        );
    }
}
