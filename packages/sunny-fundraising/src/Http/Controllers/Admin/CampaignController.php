<?php

namespace Sunny\Fundraising\Http\Controllers\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Sunny\Fundraising\Contracts\PermissionResolverContract;
use Sunny\Fundraising\Models\Campaign;
use Sunny\Fundraising\Models\CampaignContribution;

class CampaignController extends Controller
{
    public function index(Request $request, PermissionResolverContract $permissions): View
    {
        abort_unless($permissions->canManage($request->user()), 403);

        return view('fundraising::admin.index', [
            'campaigns' => Campaign::query()
                ->withCount('contributions')
                ->latest()
                ->limit(50)
                ->get(),
            'recentContributions' => CampaignContribution::query()
                ->with('campaign')
                ->latest()
                ->limit(25)
                ->get(),
        ]);
    }
}
