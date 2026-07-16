<?php

namespace ChurchTools\DigitalCounseling\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use ChurchTools\DigitalCounseling\Contracts\PermissionResolverContract;
use ChurchTools\DigitalCounseling\Models\CounselingMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CounselingMessageMediaController extends Controller
{
    public function __invoke(CounselingMessage $message, PermissionResolverContract $permissions): StreamedResponse
    {
        $user = Auth::user();
        abort_unless($user && ($permissions->canTriage($user) || $permissions->canAssign($user) || $permissions->canRespondToCase($user, $message->case)), 403);
        abort_unless($message->media_disk && $message->media_path, 404);

        return Storage::disk($message->media_disk)->response(
            $message->media_path,
            $message->metadata['original_name'] ?? null,
            [
                'Content-Type' => $message->media_mime ?: 'application/octet-stream',
                'Cache-Control' => 'no-store, private',
            ],
        );
    }
}
