<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RetiredFeatureController extends Controller
{
    public function accommodationBooking(Request $request): JsonResponse
    {
        return $this->gone(
            'Legacy accommodation booking has been retired. Goshen Retreat accommodation is now assigned by authorized staff after registration and payment.'
        );
    }

    public function manualDonation(Request $request): JsonResponse
    {
        return $this->gone(
            'Manual donation submission has been retired. Please use the Stripe-powered Giving flow for new gifts.'
        );
    }

    private function gone(string $message): JsonResponse
    {
        return response()
            ->json([
                'status' => 'retired',
                'message' => $message,
            ], 410)
            ->header('Cache-Control', 'no-store, private');
    }
}
