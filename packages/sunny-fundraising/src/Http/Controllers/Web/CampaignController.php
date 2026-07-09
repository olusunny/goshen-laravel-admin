<?php

namespace Sunny\Fundraising\Http\Controllers\Web;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Sunny\Fundraising\Models\Campaign;
use Sunny\Fundraising\Services\CampaignService;

class CampaignController extends Controller
{
    public function __construct(private readonly CampaignService $campaigns) {}

    public function index(): View
    {
        return view('fundraising::web.index', [
            'campaign' => $this->campaigns->activeCampaign(),
        ]);
    }

    public function show(string $campaign): View|Response
    {
        $record = Campaign::query()
            ->with('media')
            ->where('slug', $campaign)
            ->orWhereKey((int) $campaign)
            ->first();

        if (! $record) {
            abort(404);
        }

        return view('fundraising::web.show', [
            'campaign' => $record,
            'payload' => $this->campaigns->payload($record),
        ]);
    }
}
