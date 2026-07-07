<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

class MemberAppController extends Controller
{
    public function __invoke(): View
    {
        $enabled = filter_var(
            AppSetting::value('goshen_retreat_enabled', '1'),
            FILTER_VALIDATE_BOOLEAN
        );

        $googleWebClientId = trim((string) AppSetting::value('google_web_client_id', ''));
        $firebaseWebConfig = $this->firebaseWebConfig();

        return view($enabled ? 'member.portal' : 'member.disabled', [
            'googleLogin' => [
                'enabled' => filter_var(AppSetting::value('google_login_enabled', '0'), FILTER_VALIDATE_BOOLEAN)
                    && $googleWebClientId !== '',
                'clientId' => $googleWebClientId,
                'firebase' => [
                    'enabled' => collect($firebaseWebConfig)
                        ->only(['apiKey', 'authDomain', 'projectId', 'appId'])
                        ->every(fn ($value): bool => filled($value)),
                    'config' => $firebaseWebConfig,
                ],
            ],
        ]);
    }

    private function firebaseWebConfig(): array
    {
        return [
            'apiKey' => (string) AppSetting::value('firebase_web_api_key', 'AIzaSyBpZrM5EJ0a7_4A2mm-96i3EAJ9_GJRi3s'),
            'authDomain' => (string) AppSetting::value('firebase_web_auth_domain', 'mfm-triumphant-church-apps.firebaseapp.com'),
            'projectId' => (string) AppSetting::value('firebase_web_project_id', 'mfm-triumphant-church-apps'),
            'storageBucket' => (string) AppSetting::value('firebase_web_storage_bucket', 'mfm-triumphant-church-apps.firebasestorage.app'),
            'messagingSenderId' => (string) AppSetting::value('firebase_web_messaging_sender_id', '245162281677'),
            'appId' => (string) AppSetting::value('firebase_web_app_id', '1:245162281677:web:cf1df7affcc5a4cb3eb784'),
            'measurementId' => (string) AppSetting::value('firebase_web_measurement_id', 'G-385V4SVSLH'),
        ];
    }
}
