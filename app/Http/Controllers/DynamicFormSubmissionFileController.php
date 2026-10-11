<?php

namespace App\Http\Controllers;

use App\Models\DynamicFormSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DynamicFormSubmissionFileController extends Controller
{
    public function show(Request $request, DynamicFormSubmission $submission, string $field): StreamedResponse|Response
    {
        abort_unless(\App\Filament\Resources\DynamicFormSubmissionResource::canView($submission) || $request->hasValidSignature(), 403);

        $answer = $submission->attachmentForField($field);
        abort_unless($answer !== null, 404);

        $disk = $answer['disk'];
        $path = $answer['file_path'];
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download(
            $path,
            (string) ($answer['original_name'] ?? basename($path)),
        );
    }
}
