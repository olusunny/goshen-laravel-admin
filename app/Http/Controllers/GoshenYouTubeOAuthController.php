<?php

namespace App\Http\Controllers;

use App\Models\GoshenYouTubeConnection;
use App\Services\YouTube\GoogleYouTubeTokenProvider;
use App\Services\YouTube\YouTubeGateway;
use App\Services\YouTube\YouTubeOAuthStateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoshenYouTubeOAuthController extends Controller
{
    public function redirect(Request $request, GoogleYouTubeTokenProvider $tokens, YouTubeOAuthStateService $states): RedirectResponse
    {
        $connectionId = $request->integer('connection') ?: null;
        $state = $states->create((int) $request->user()->id, $connectionId);

        try {
            return redirect()->away($tokens->authorizationUrl($state['state'], $state['code_verifier']));
        } catch (Throwable $exception) {
            report($exception);

            return $this->redirectToConnections()->withErrors([
                'youtube' => 'The server-side YouTube connection is not configured.',
            ]);
        }
    }

    public function callback(
        Request $request,
        GoogleYouTubeTokenProvider $tokens,
        YouTubeGateway $gateway,
        YouTubeOAuthStateService $states,
    ): RedirectResponse {
        if ($request->filled('error')) {
            return $this->redirectToConnections()->withErrors([
                'youtube' => 'The YouTube channel authorization was not completed.',
            ]);
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:4096'],
            'state' => ['required', 'string', 'size:80'],
        ]);

        $state = $states->consume($data['state'], (int) $request->user()->id);

        try {
            $token = $tokens->exchangeAuthorizationCode($data['code'], $state['code_verifier']);
            $channel = $gateway->currentChannel((string) $token['access_token']);
            $connection = $state['connection_id']
                ? GoshenYouTubeConnection::query()->findOrFail($state['connection_id'])
                : GoshenYouTubeConnection::query()->firstOrNew(['channel_id' => $channel->id]);

            $connection->forceFill([
                'channel_id' => $channel->id,
                'channel_title' => $channel->title,
                'scopes' => config('goshen_experience.youtube.scopes'),
                'default_privacy' => config('goshen_experience.youtube.default_privacy'),
                'health' => GoshenYouTubeConnection::HEALTH_HEALTHY,
                'refresh_token_payload' => $token,
                'connected_by_id' => $request->user()->id,
                'connected_at' => now(),
                'last_checked_at' => now(),
                'last_error_code' => null,
                'last_error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            Log::warning('Goshen YouTube channel connection failed.', [
                'admin_id' => $request->user()->id,
                'exception' => $exception::class,
            ]);

            return $this->redirectToConnections()->withErrors([
                'youtube' => 'The YouTube channel could not be connected. Please review the server configuration and try again.',
            ]);
        }

        return $this->redirectToConnections()->with('status', 'YouTube channel connected.');
    }

    private function redirectToConnections(): RedirectResponse
    {
        return redirect()->route('filament.admin.resources.goshen-youtube-connections.index');
    }
}
