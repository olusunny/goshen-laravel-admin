<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommunityPrayerRequestResource;
use App\Models\CommunityPrayerRequest;
use App\Models\User;
use Illuminate\Http\Request;

class AdminCommunityPrayerRequestController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $requests = CommunityPrayerRequest::query()
            ->with(['comments', 'flags', 'suggestions', 'mobileUser'])
            ->when($request->input('status') === 'hidden', fn ($query) => $query->whereNotNull('hidden_at'))
            ->when($request->input('status') === 'visible', fn ($query) => $query->whereNull('hidden_at')->where('expires_at', '>', now()))
            ->when($request->input('status') === 'expired', fn ($query) => $query->expired())
            ->latest()
            ->paginate((int) $request->integer('per_page', 25));

        return CommunityPrayerRequestResource::collection($requests);
    }

    public function hide(Request $request, CommunityPrayerRequest $communityPrayerRequest)
    {
        $admin = $this->authorizeAdmin($request);
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:120'],
        ]);

        $communityPrayerRequest->hide($data['reason'] ?? 'admin_hidden', $admin->id);

        return new CommunityPrayerRequestResource($communityPrayerRequest->fresh());
    }

    public function restore(Request $request, CommunityPrayerRequest $communityPrayerRequest)
    {
        $admin = $this->authorizeAdmin($request);

        $communityPrayerRequest->forceFill([
            'hidden_at' => null,
            'hidden_reason' => null,
            'moderated_by' => $admin->id,
            'moderated_at' => now(),
        ])->save();

        return new CommunityPrayerRequestResource($communityPrayerRequest->fresh());
    }

    public function export(Request $request)
    {
        $this->authorizeAdmin($request);

        $filename = 'interactive-prayer-requests-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'id',
                'type',
                'text',
                'audio_path',
                'audio_duration_seconds',
                'flags_count',
                'comments_count',
                'hidden_reason',
                'created_at',
                'expires_at',
            ]);

            CommunityPrayerRequest::query()
                ->latest()
                ->chunk(200, function ($requests) use ($output) {
                    foreach ($requests as $request) {
                        fputcsv($output, [
                            $request->id,
                            $request->type,
                            $request->text,
                            $request->audio_path,
                            $request->audio_duration_seconds,
                            $request->flags_count,
                            $request->comments_count,
                            $request->hidden_reason,
                            $request->created_at,
                            $request->expires_at,
                        ]);
                    }
                });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function authorizeAdmin(Request $request): User
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $user->hasAnyRole(['super_admin', 'moderator', 'content_manager']),
            403,
            'Only moderators can manage interactive prayer requests.'
        );

        return $user;
    }
}
