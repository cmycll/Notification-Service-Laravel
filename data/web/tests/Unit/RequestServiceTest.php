<?php

namespace Tests\Unit;

use App\Models\NotificationMessageModel;
use App\Models\NotificationRequestModel;
use App\Models\User;
use App\Services\Notifications\DTO\NotificationRequest;
use App\Services\Notifications\RequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

class RequestServiceTest extends TestCase
{
    use RefreshDatabase;

    public function testValidateRequestDataGeneratesIdempotencyKeyWhenMissing(): void
    {
        $request = Request::create('/api/notifications', 'POST', [
            'channel' => 'sms',
            'priority' => 'normal',
            'template' => [
                'subject' => 'Hello',
                'body' => 'Test body',
            ],
            'recipients' => [
                ['to' => '+905551234567', 'vars' => []],
            ],
        ]);

        $service = app(RequestService::class);
        $validated = $service->validateRequestData($request);

        $this->assertArrayHasKey('idempotency_key', $validated);
        $this->assertNotEmpty($validated['idempotency_key']);
        $this->assertStringStartsWith('i-', $validated['idempotency_key']);
    }

    public function testCreateRequestRecordPersistsRequestAndMessages(): void
    {
        $user = User::factory()->create();
        Auth::login($user);

        $request = Request::create('/api/notifications', 'POST');
        $request->headers->set('X-Correlation-ID', (string) Str::uuid());
        $this->app->instance('request', $request);

        $dto = new NotificationRequest(
            template: ['subject' => 'Hello', 'body' => 'Test body'],
            recipients: [
                ['to' => '+905551234567', 'vars' => []],
                ['to' => '+905551234568', 'vars' => []],
            ],
            channel: 'sms',
            priority: 'normal',
            idempotencyKey: null,
            scheduledAt: null,
            requestedCount: 2,
            acceptedCount: 2,
            rejectedCount: 0
        );

        $service = app(RequestService::class);
        $requestId = $service->createRequestRecord($dto);

        $this->assertDatabaseHas('notif_requests', [
            'id' => $requestId,
            'client_id' => $user->id,
            'idempotency_key' => null,
        ]);

        $this->assertSame(
            2,
            NotificationMessageModel::where('request_id', $requestId)->count()
        );

        $request = NotificationRequestModel::find($requestId);
        $this->assertSame(2, $request->pending_count);
    }
}
