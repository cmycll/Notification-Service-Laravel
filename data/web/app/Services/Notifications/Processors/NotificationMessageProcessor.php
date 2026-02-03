<?php

namespace App\Services\Notifications\Processors;

use App\Models\NotificationMessageModel;
use App\Models\NotificationRequestModel;
use App\Services\Notifications\Processors\ChannelHandlerFactory;
use App\Services\Notifications\Processors\ChannelHandlers\AbstractChannelHandler;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Enums\StatusTypeEnum;

class NotificationMessageProcessor
{
    public function __construct(
        protected ChannelHandlerFactory $factory
    ) {
    }

    /**
     * Process the notification message
     * @param string $messageId
     * @return void
     * @throws \RuntimeException
     */
    public function handle(string $messageId): void
    {
        try {
            $notification = $this->getNotification($messageId);

            if (!$notification) {
                throw new \RuntimeException("Notification not found");
            }

            $channel = $notification['message']['channel'];
            $status = $notification['message']['status'] ?? null;
            if (
                \in_array($status, [
                StatusTypeEnum::SENT->value,
                StatusTypeEnum::FAILED->value,
                StatusTypeEnum::CANCELLED->value,
                ], true)
            ) {
                return;
            }

            Redis::throttle("{$channel}-rate-limit")
                ->allow(100)
                ->every(1)
                ->then(function () use ($notification) {
                    $message = $notification['message'];
                    if (($message['status'] ?? null) === StatusTypeEnum::PENDING->value) {
                        NotificationMessageModel::where('id', $message['id'])->update([
                            'status' => StatusTypeEnum::PROCESSING,
                            'attempts' => ($message['attempts'] ?? 0) + 1,
                        ]);
                    }

                    /** @var AbstractChannelHandler $handler */
                    $handler = $this->factory->make($notification['message']['channel']);

                    if (!$handler) {
                        throw new \RuntimeException("No handler for channel: {$notification['message']['channel']}");
                    }

                    $handler->handle($notification);
                }, function () {
                    throw new \RuntimeException("Rate limit exceeded");
                });
        } catch (\Throwable $th) {
            Log::error("Failed to process notification message: {$th->getMessage()}");
            throw $th;
        }
    }

    /**
     * Get the notification message and request
     * @param string $notificationID
     * @return array|null
     */
    private function getNotification(string $notificationID): ?array
    {
        $message = NotificationMessageModel::find($notificationID);
        if (empty($message)) {
            Log::error("Notification message not found", ['message_id' => $notificationID]);
            return null;
        }

        $request = NotificationRequestModel::find($message->request_id);
        if (empty($request)) {
            Log::error("Notification request not found", ['request_id' => $message->request_id]);
            return null;
        }

        return [
            'message' => $message->toArray(),
            'request' => $request->toArray(),
        ];
    }
}
