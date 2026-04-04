<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController
{
    public function check(): JsonResponse
    {
        $services = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
        ];

        $allHealthy = !in_array('error', $services, true);

        return response()->json([
            'status' => $allHealthy ? 'ok' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'services' => $services,
        ], $allHealthy ? 200 : 503);
    }

    private function checkDatabase(): string
    {
        try {
            DB::connection()->getPdo();
            return 'ok';
        } catch (\Exception $e) {
            return 'error';
        }
    }

    private function checkRedis(): string
    {
        try {
            Redis::ping();
            return 'ok';
        } catch (\Exception $e) {
            return 'error';
        }
    }

    private function checkQueue(): string
    {
        try {
            $queueDriver = config('queue.default');
            if ($queueDriver === 'redis') {
                Redis::ping();
            } elseif ($queueDriver === 'database') {
                DB::connection()->getPdo();
            }
            return 'ok';
        } catch (\Exception $e) {
            return 'error';
        }
    }
}
