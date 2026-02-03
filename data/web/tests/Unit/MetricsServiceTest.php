<?php

namespace Tests\Unit;

use App\Services\Notifications\MetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function testMetricsServiceComputesRatesAndLatency(): void
    {
        $requestId = (string) Str::uuid7();
        $messageSentId = (string) Str::uuid7();
        $messageFailedId = (string) Str::uuid7();

        $createdAt = now()->subMinutes(10);
        $sentAt = $createdAt->copy()->addSeconds(60);
        $failedAt = $createdAt->copy()->addSeconds(120);

        DB::table('notif_requests')->insert([
            'id' => $requestId,
            'client_id' => 1,
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
                'id' => $messageSentId,
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
                'id' => $messageFailedId,
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

        $metrics = app(MetricsService::class)->getSummary(60);

        $this->assertSame(50.0, $metrics['rates']['success_rate_percent']);
        $this->assertSame(50.0, $metrics['rates']['failure_rate_percent']);
        $this->assertSame(90.0, $metrics['latency_seconds_avg']);
        $this->assertSame('00:01:30', $metrics['latency_avg_human']);
    }
}
