<?php

namespace Tests\Feature;

use App\Enums\StatusTypeEnum;
use App\Models\NotificationMessageModel;
use App\Models\NotificationRequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private function makePayload(array $overrides = []): array
    {
        return array_merge([
            'channel' => 'sms',
            'priority' => 'normal',
            'template' => [
                'subject' => 'Hello',
                'body' => 'Test message',
            ],
            'recipients' => [
                ['to' => '+905551234567', 'vars' => []],
                ['to' => '+905551234568', 'vars' => []],
            ],
            'scheduled_at' => now()->addMinutes(10)->toDateTimeString(),
        ], $overrides);
    }

    public function testCreateNotificationWithScheduleCreatesRequestAndMessages(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/notifications', $this->makePayload());

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['request_id']);

        $this->assertDatabaseCount('notif_requests', 1);
        $this->assertDatabaseCount('notif_request_notifications', 2);
    }

    public function testListNotificationsReturnsRequests(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/notifications', $this->makePayload(['idempotency_key' => 'req-1']));
        $this->postJson('/api/notifications', $this->makePayload(['idempotency_key' => 'req-2']));

        $response = $this->getJson('/api/notifications');

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data');
    }

    public function testGetNotificationsReturnsRequestMessages(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/notifications', $this->makePayload());
        $requestId = $create->json('request_id');

        $response = $this->getJson("/api/notifications/{$requestId}");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data.messages');
    }

    public function testCancelMessageUpdatesStatusAndCounts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/notifications', $this->makePayload());
        $requestId = $create->json('request_id');
        $messageId = NotificationMessageModel::where('request_id', $requestId)->value('id');

        $response = $this->postJson("/api/notifications/message/{$messageId}/cancel");

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.status', StatusTypeEnum::CANCELLED->value);

        $this->assertDatabaseHas('notif_request_notifications', [
            'id' => $messageId,
            'status' => StatusTypeEnum::CANCELLED->value,
        ]);
    }

    public function testCancelRequestCancelsAllPendingMessages(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/notifications', $this->makePayload());
        $requestId = $create->json('request_id');

        $response = $this->postJson("/api/notifications/request/{$requestId}/cancel");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('notif_requests', [
            'id' => $requestId,
            'status' => StatusTypeEnum::CANCELLED->value,
        ]);

        $this->assertSame(
            0,
            NotificationMessageModel::where('request_id', $requestId)
                ->where('status', StatusTypeEnum::PENDING->value)
                ->count()
        );
    }

    public function testSmsBodyLengthLimitIsEnforced(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = $this->makePayload([
            'template' => [
                'subject' => 'Hello',
                'body' => str_repeat('a', 161),
            ],
        ]);

        $response = $this->postJson('/api/notifications', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['template.body']);
    }
}
