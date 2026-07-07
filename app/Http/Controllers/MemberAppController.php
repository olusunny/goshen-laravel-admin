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

        return view($enabled ? 'member.portal' : 'member.disabled', [
            'googleLogin' => [
                'enabled' => filter_var(AppSetting::value('google_login_enabled', '0'), FILTER_VALIDATE_BOOLEAN)
                    && $googleWebClientId !== '',
                'clientId' => $googleWebClientId,
            ],
        ]);
    }
}
