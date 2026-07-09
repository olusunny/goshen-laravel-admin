<?php

namespace ChurchTools\CloudBackup\Http\Controllers;

use ChurchTools\CloudBackup\Models\CloudBackupConnection;
use ChurchTools\CloudBackup\Services\Cloud\CloudProviderFactory;
use ChurchTools\CloudBackup\Services\OAuthStateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;

class OAuthController extends Controller
{
    public function redirect(Request $request, string $provider, CloudProviderFactory $providers, OAuthStateService $states): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'folder_path' => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless(in_array($provider, ['google', 'onedrive'], true), 404);

        $state = $states->create([
            'provider' => $provider,
            'name' => $data['name'] ?? ucfirst($provider).' backup',
            'folder_path' => $data['folder_path'] ?? 'LaravelBackups',
        ]);

        try {
            return redirect()->away($providers->make($provider)->authorizationUrl($state));
        } catch (\RuntimeException $exception) {
            return redirect()
                ->to($this->consoleUrl())
                ->withErrors(['oauth' => $exception->getMessage()]);
        }
    }

    public function callback(Request $request, string $provider, CloudProviderFactory $providers, OAuthStateService $states): RedirectResponse
    {
        abort_unless(in_array($provider, ['google', 'onedrive'], true), 404);

        if ($request->filled('error')) {
            return redirect()
                ->to($this->consoleUrl())
                ->withErrors(['oauth' => 'Cloud authorization failed: '.$request->string('error')->limit(120)]);
        }

        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        $payload = $states->consume((string) $request->query('state'));
        abort_unless(($payload['provider'] ?? null) === $provider, 403);

        $providerClient = $providers->make($provider);
        try {
            $result = $providerClient->exchangeCode((string) $request->query('code'));
        } catch (\RuntimeException $exception) {
            return redirect()
                ->to($this->consoleUrl())
                ->withErrors(['oauth' => $exception->getMessage()]);
        }

        CloudBackupConnection::create([
            'name' => $payload['name'],
            'provider' => $provider,
            'owner_name' => $result['account']['name'] ?? null,
            'owner_email' => $result['account']['email'] ?? null,
            'folder_path' => $payload['folder_path'],
            'token_payload' => $result['token'],
            'scopes' => config("cloud-backup.oauth.{$provider}.scopes", []),
            'connected_at' => now(),
        ]);

        return redirect()
            ->to($this->consoleUrl())
            ->with('status', ucfirst($provider).' connected successfully.');
    }

    private function consoleUrl(): string
    {
        return Route::has('filament.admin.pages.cloud-backup-console')
            ? route('filament.admin.pages.cloud-backup-console')
            : route('cloud-backup.index');
    }
}
