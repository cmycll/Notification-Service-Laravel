<?php

namespace App\Services\Notifications;

use App\Enums\StatusTypeEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Keeps request/batch counters in Redis (db 3); periodically flushes to MySQL.
 * When a message is SENT/FAILED only Redis is updated; MySQL load is reduced.
 */
final class RequestCounterRedisService
{
    private const CONNECTION = 'counters';

    private const KEY_PREFIX = 'request_counters:';

    private const DIRTY_SET_KEY = 'request_counters:dirty';

    /** Increments SENT count in Redis for the request; adds request_id to dirty set. */
    public function incrementSent(string $requestId): void
    {
        $redis = Redis::connection(self::CONNECTION);
        $key = self::KEY_PREFIX . $requestId;
        $redis->hIncrBy($key, 'sent', 1);
        $redis->sAdd(self::DIRTY_SET_KEY, $requestId);
    }

    /** Increments FAILED count in Redis for the request; adds request_id to dirty set. */
    public function incrementFailed(string $requestId): void
    {
        $redis = Redis::connection(self::CONNECTION);
        $key = self::KEY_PREFIX . $requestId;
        $redis->hIncrBy($key, 'failed', 1);
        $redis->sAdd(self::DIRTY_SET_KEY, $requestId);
    }

    /**
     * For all request_ids in the dirty set: reads and clears deltas from Redis, writes to MySQL.
     * Uses Lua for atomic read+clear.
     *
     * @return array{flushed: int, errors: int}
     */
    public function flushToMysql(): array
    {
        $redis = Redis::connection(self::CONNECTION);
        $requestIds = $redis->sMembers(self::DIRTY_SET_KEY);

        if (empty($requestIds)) {
            return ['flushed' => 0, 'errors' => 0];
        }

        $lua = $this->getReadAndClearScript();
        $flushed = 0;
        $errors = 0;

        foreach ($requestIds as $requestId) {
            $key = self::KEY_PREFIX . $requestId;
            try {
                $result = $redis->eval($lua, 1, $key);
                $sentDelta = (int) ($result[0] ?? 0);
                $failedDelta = (int) ($result[1] ?? 0);

                if ($sentDelta === 0 && $failedDelta === 0) {
                    $redis->sRem(self::DIRTY_SET_KEY, $requestId);
                    continue;
                }

                DB::table('notif_requests')
                    ->where('id', $requestId)
                    ->update([
                        'sent_count' => DB::raw('sent_count + ' . $sentDelta),
                        'failed_count' => DB::raw('failed_count + ' . $failedDelta),
                        'pending_count' => DB::raw('pending_count - ' . ($sentDelta + $failedDelta)),
                    ]);

                DB::table('notif_requests')
                    ->where('id', $requestId)
                    ->where('pending_count', 0)
                    ->update(['status' => StatusTypeEnum::SENT->value]);

                $redis->sRem(self::DIRTY_SET_KEY, $requestId);
                $flushed++;
            } catch (\Throwable $e) {
                $errors++;
                report($e);
            }
        }

        return ['flushed' => $flushed, 'errors' => $errors];
    }

    /** Lua: read hash at key, delete it, return sent and failed values. */
    private function getReadAndClearScript(): string
    {
        return <<<'LUA'
local key = KEYS[1]
local sent = redis.call('HGET', key, 'sent')
local failed = redis.call('HGET', key, 'failed')
if sent == false then sent = '0' end
if failed == false then failed = '0' end
redis.call('DEL', key)
return {sent, failed}
LUA;
    }
}
