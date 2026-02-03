<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function __invoke(Request $request)
    {
        $checks = [
            'database' => ['ok' => false],
            'redis' => ['ok' => false],
        ];

        try {
            DB::connection()->select('select 1');
            $checks['database']['ok'] = true;
        } catch (\Throwable $e) {
            $checks['database']['error'] = $e->getMessage();
        }

        try {
            $redis = Redis::connection();
            $pong = $redis->ping();
            $checks['redis']['ok'] = $pong === true || $pong === 'PONG';
        } catch (\Throwable $e) {
            $checks['redis']['error'] = $e->getMessage();
        }

        $allOk = $checks['database']['ok'] && $checks['redis']['ok'];
        $status = $allOk ? 'ok' : 'degraded';

        return response()->json([
            'status' => $status,
            'checks' => $checks,
        ], $allOk ? 200 : 503);
    }
}
