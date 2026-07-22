<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MobileUser;
use App\Services\Addons\AddonCapabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddonCapabilityController extends Controller
{
    public function index(Request $request, AddonCapabilityService $capabilities): JsonResponse
    {
        $user = $request->user('mobile') ?? $request->user();

        abort_unless(
            $user instanceof MobileUser && $user->canUseCommunity(),
            403,
            'Only verified mobile users can discover add-on capabilities.'
        );

        return response()->json([
            'data' => [
                'capabilities' => $capabilities->forMobileUser($user),
            ],
        ]);
    }
}
