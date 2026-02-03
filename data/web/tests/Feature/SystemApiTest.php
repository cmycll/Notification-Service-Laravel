<?php

namespace Tests\Feature;

use App\Models\NotificationRequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class SystemApiTest extends TestCase
{
    use RefreshDatabase;

    public function testMetricsEndpointReturnsPayload(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $requestId = (string) Str::uuid7();
        $createdAt = now()->subMinutes(10);
        $sentAt = $createdAt->copy()->addSeconds(60);
        $failedAt = $createdAt->copy()->addSeconds(120);

        NotificationRequestModel::create([
            'id' => $requestId,
            'client_id' => $user->id,
            'idempotency_key' => null,
            'correlation_id' => (string) Str::uuid(),
            'template_subject' => 'Test',
            'template_body_path' => '',
            'template_body_inline' => 'Body',
            'requested_count' => 2,
            'accepted_count' => 2,
            'pending_count' => 0,
            'sent_count' => 1,
            'failed_count' => 1,
            'cancelled_count' => 0,
            'channel' => 'sms',
            'priority' => 'normal',
            'status' => 'sent',
            'scheduled_at' => null,
            'created_at' => $createdAt,
        ]);

        DB::table('notif_request_notifications')->insert([
            [
                'id' => (string) Str::uuid7(),
                'request_id' => $requestId,
                'to' => '+905551234567',
                'vars' => json_encode([]),
                'channel' => 'sms',
                'priority' => 'normal',
                'status' => 'sent',
                'delivery_state' => 'queued',
                'attempts' => 1,
                'provider_message_id' => null,
                'last_error' => null,
                'created_at' => $createdAt,
                'updated_at' => $sentAt,
            ],
            [
                'id' => (string) Str::uuid7(),
                'request_id' => $requestId,
                'to' => '+905551234568',
                'vars' => json_encode([]),
                'channel' => 'sms',
                'priority' => 'normal',
                'status' => 'failed',
                'delivery_state' => 'failed',
                'attempts' => 1,
                'provider_message_id' => null,
                'last_error' => 'fail',
                'created_at' => $createdAt,
                'updated_at' => $failedAt,
            ],
        ]);

        $redisMock = Mockery::mock();
        $redisMock->shouldReceive('llen')->andReturn(0);
        $redisMock->shouldReceive('zcard')->andReturn(0);
        Redis::shouldReceive('connection')->andReturn($redisMock);

        $response = $this->getJson('/api/system/metrics');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'window_minutes',
                'queues' => [
                    'connection',
                    'queues',
                ],
                'requests' => [
                    'pending',
                    'processing',
                    'sent',
                    'failed',
                    'cancelled',
                ],
                'messages' => [
                    'pending',
                    'processing',
                    'sent',
                    'failed',
                    'cancelled',
                ],
                'rates' => [
                    'success_rate_percent',
                    'failure_rate_percent',
                ],
                'latency_seconds_avg',
                'latency_avg_human',
            ]);

        $response->assertJsonPath('rates.success_rate_percent', 50);
        $response->assertJsonPath('rates.failure_rate_percent', 50);
        $response->assertJsonPath('latency_seconds_avg', 90);
    }
}
