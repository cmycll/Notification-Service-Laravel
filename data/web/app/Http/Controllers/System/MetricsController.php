<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\Notifications\MetricsService;
use Illuminate\Http\Request;

class MetricsController extends Controller
{
    public function __invoke(Request $request, MetricsService $metricsService)
    {
        $validated = $request->validate([
            'window_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
        ]);

        $windowMinutes = $validated['window_minutes'] ?? 60;
        $metrics = $metricsService->getSummary($windowMinutes);

        return response()->json([
            'success' => true,
            ...$metrics,
        ]);
    }
}
