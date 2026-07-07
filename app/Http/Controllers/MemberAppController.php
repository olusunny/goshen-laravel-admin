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

        return view($enabled ? 'member.portal' : 'member.disabled');
    }
}
