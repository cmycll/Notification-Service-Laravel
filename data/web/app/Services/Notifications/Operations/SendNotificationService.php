<?php

namespace App\Services\Notifications\Operations;

use App\Models\NotificationMessageModel;
use App\Jobs\SendNotificationMessageJob;
use Illuminate\Support\Facades\Log;

class SendNotificationService
{
    public function addToQueue(string $requestId): bool
    {
        try {
            $messages = NotificationMessageModel::where('request_id', $requestId)->get();
            foreach ($messages as $message) {
                retry(3, function () use ($message) {
                    $priority = $message->priority instanceof \BackedEnum
                        ? $message->priority->value
                        : (string) $message->priority;
                    SendNotificationMessageJob::dispatch($message->id)
                        ->onQueue($priority);
                }, 200);
            }

            return true;
        } catch (\Throwable $th) {
            Log::error("FAILED_TO_ADD_NOTIFICATION_TO_QUEUE", [
                'request_id' => $requestId,
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            throw new \Exception("Failed to add notification to queue: {$th->getMessage()}");
        }
    }
}
