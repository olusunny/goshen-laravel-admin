<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SplashMediaService;
use Illuminate\Http\JsonResponse;

class AppSplashMediaController extends Controller
{
    public function show(SplashMediaService $service): JsonResponse
    {
        return response()->json($service->publicPayload());
    }
}
