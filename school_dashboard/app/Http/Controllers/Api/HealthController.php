<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Lightweight liveness probe used by Render's health check.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status'  => 'ok',
            'service' => 'Algerian School Support API',
            'time'    => now('UTC')->toIso8601String(),
        ]);
    }
}