<?php

namespace Tests\Unit;

use App\Enums\StatusTypeEnum;
use App\Models\NotificationMessageModel;
use App\Models\NotificationRequestModel;
use App\Services\Notifications\Processors\ChannelHandlerFactory;
use App\Services\Notifications\Processors\ChannelHandlers\AbstractChannelHandler;
use App\Services\Notifications\Processors\NotificationMessageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class NotificationMessageProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function testItSkipsAlreadyCompletedMessages(): void
    {
        $requestId = (string) Str::uuid7();
        NotificationRequestModel::create([
            'id' => $requestId,
            'client_id' => 1,
            'idempotency_key' => null,
            'correlation_id' => (string) Str::uuid(),
            'template_subject' => 'Hello',
            'template_body_path' => '',
            'template_body_inline' => 'Body',
            'requested_count' => 1,
            'accepted_count' => 1,
            'pending_count' => 0,
            'channel' => 'sms',
            'priority' => 'normal',
            'status' => StatusTypeEnum::SENT,
            'scheduled_at' => null,
        ]);

        $message = NotificationMessageModel::create([
            'id' => (string) Str::uuid7(),
            'request_id' => $requestId,
            'to' => '+905551234567',
            'vars' => [],
            'channel' => 'sms',
            'priority' => 'normal',
            'status' => StatusTypeEnum::SENT,
            'delivery_state' => 'queued',
            'attempts' => 0,
        ]);

        Redis::shouldReceive('throttle')->never();

        $processor = app(NotificationMessageProcessor::class);
        $processor->handle($message->id);

        $this->assertSame(StatusTypeEnum::SENT->value, $message->fresh()->status->value);
    }

    public function testItProcessesPendingMessageAndCallsHandler(): void
    {
        $requestId = (string) Str::uuid7();
        NotificationRequestModel::create([
            'id' => $requestId,
            'client_id' => 1,
            'idempotency_key' => null,
            'correlation_id' => (string) Str::uuid(),
            'template_subject' => 'Hello',
            'template_body_path' => '',
            'template_body_inline' => 'Body',
            'requested_count' => 1,
            'accepted_count' => 1,
            'pending_count' => 1,
            'channel' => 'sms',
            'priority' => 'normal',
            'status' => StatusTypeEnum::PENDING,
            'scheduled_at' => null,
        ]);

        $message = NotificationMessageModel::create([
            'id' => (string) Str::uuid7(),
            'request_id' => $requestId,
            'to' => '+905551234567',
            'vars' => [],
            'channel' => 'sms',
            'priority' => 'normal',
            'status' => StatusTypeEnum::PENDING,
            'delivery_state' => 'queued',
            'attempts' => 0,
        ]);

        $handler = Mockery::mock(AbstractChannelHandler::class);
        $handler->shouldReceive('handle')->once();

        $factory = Mockery::mock(ChannelHandlerFactory::class);
        $factory->shouldReceive('make')->with('sms')->andReturn($handler);
        $this->app->instance(ChannelHandlerFactory::class, $factory);

        $throttle = Mockery::mock();
        $throttle->shouldReceive('allow')->andReturnSelf();
        $throttle->shouldReceive('every')->andReturnSelf();
        $throttle->shouldReceive('then')->once()->andReturnUsing(function ($success, $fail) {
            $success();
        });
        Redis::shouldReceive('throttle')->once()->andReturn($throttle);

        $processor = app(NotificationMessageProcessor::class);
        $processor->handle($message->id);

        $this->assertSame(StatusTypeEnum::PROCESSING->value, $message->fresh()->status->value);
    }
}
