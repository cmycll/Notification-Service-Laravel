<?php

namespace App\Services\Notifications;

use App\Enums\StatusTypeEnum;
use App\Models\NotificationMessageModel;
use App\Models\NotificationRequestModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class MetricsService
{
    public function getSummary(int $windowMinutes = 60): array
    {
        $since = now()->subMinutes($windowMinutes);

        $requestCounts = NotificationRequestModel::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $messageBaseQuery = NotificationMessageModel::query()
            ->whereHas('request');

        $messageCounts = (clone $messageBaseQuery)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $sentCount = (clone $messageBaseQuery)
            ->where('status', StatusTypeEnum::SENT->value)
            ->where('created_at', '>=', $since)
            ->count();

        $failedCount = (clone $messageBaseQuery)
            ->where('status', StatusTypeEnum::FAILED->value)
            ->where('created_at', '>=', $since)
            ->count();

        $totalCompleted = $sentCount + $failedCount;
        if ($totalCompleted > 0) {
            $successRate = round(($sentCount / $totalCompleted) * 100, 2);
            $failureRate = round(($failedCount / $totalCompleted) * 100, 2);
        } else {
            $overallSent = (int) ($messageCounts[StatusTypeEnum::SENT->value] ?? 0);
            $overallFailed = (int) ($messageCounts[StatusTypeEnum::FAILED->value] ?? 0);
            $overallTotal = $overallSent + $overallFailed;
            $successRate = $overallTotal > 0 ? round(($overallSent / $overallTotal) * 100, 2) : 0.0;
            $failureRate = $overallTotal > 0 ? round(($overallFailed / $overallTotal) * 100, 2) : 0.0;
        }

        $latencyExpr = $this->latencyExpression();
        $latencyAvgSeconds = (clone $messageBaseQuery)
            ->whereIn('status', [StatusTypeEnum::SENT->value, StatusTypeEnum::FAILED->value])
            ->whereNotNull('updated_at')
            ->where('created_at', '>=', $since)
            ->selectRaw("AVG({$latencyExpr}) as avg_latency")
            ->value('avg_latency');

        if ($latencyAvgSeconds === null) {
            $latencyAvgSeconds = (clone $messageBaseQuery)
                ->whereIn('status', [StatusTypeEnum::SENT->value, StatusTypeEnum::FAILED->value])
                ->whereNotNull('updated_at')
                ->selectRaw("AVG({$latencyExpr}) as avg_latency")
                ->value('avg_latency');
        }

        $queueDepth = $this->getQueueDepths();

        $latencySecondsValue = $latencyAvgSeconds !== null ? round((float) $latencyAvgSeconds, 2) : 0.0;
        $latencyHuman = gmdate('H:i:s', (int) round($latencySecondsValue));

        return [
            'window_minutes' => $windowMinutes,
            'queues' => $queueDepth,
            'requests' => [
                'pending' => (int) ($requestCounts[StatusTypeEnum::PENDING->value] ?? 0),
                'processing' => (int) ($requestCounts[StatusTypeEnum::PROCESSING->value] ?? 0),
                'sent' => (int) ($requestCounts[StatusTypeEnum::SENT->value] ?? 0),
                'failed' => (int) ($requestCounts[StatusTypeEnum::FAILED->value] ?? 0),
                'cancelled' => (int) ($requestCounts[StatusTypeEnum::CANCELLED->value] ?? 0),
            ],
            'messages' => [
                'pending' => (int) ($messageCounts[StatusTypeEnum::PENDING->value] ?? 0),
                'processing' => (int) ($messageCounts[StatusTypeEnum::PROCESSING->value] ?? 0),
                'sent' => (int) ($messageCounts[StatusTypeEnum::SENT->value] ?? 0),
                'failed' => (int) ($messageCounts[StatusTypeEnum::FAILED->value] ?? 0),
                'cancelled' => (int) ($messageCounts[StatusTypeEnum::CANCELLED->value] ?? 0),
            ],
            'rates' => [
                'success_rate_percent' => $successRate,
                'failure_rate_percent' => $failureRate,
            ],
            'latency_seconds_avg' => $latencySecondsValue,
            'latency_avg_human' => $latencyHuman,
        ];
    }

    private function getQueueDepths(): array
    {
        $connection = config('queue.default');
        if ($connection !== 'redis') {
            return [
                'connection' => $connection,
                'queues' => [],
            ];
        }

        $redisConnection = config('queue.connections.redis.connection', 'default');
        $defaultQueue = config('queue.connections.redis.queue', 'default');
        $queues = array_values(array_unique([$defaultQueue, 'high', 'normal', 'low']));

        $redis = Redis::connection($redisConnection);
        $queueStats = [];

        foreach ($queues as $queue) {
            $queueStats[$queue] = [
                'ready' => (int) $redis->llen("queues:{$queue}"),
                'delayed' => (int) $redis->zcard("queues:{$queue}:delayed"),
                'reserved' => (int) $redis->zcard("queues:{$queue}:reserved"),
            ];
        }

        return [
            'connection' => $connection,
            'queues' => $queueStats,
        ];
    }

    private function latencyExpression(): string
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            return '(julianday(updated_at) - julianday(created_at)) * 86400';
        }

        return 'TIMESTAMPDIFF(SECOND, created_at, updated_at)';
    }
}
