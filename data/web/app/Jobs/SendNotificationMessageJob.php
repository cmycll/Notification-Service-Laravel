<?php

namespace App\Jobs;

use App\Enums\DeliveryStateEnum;
use App\Enums\StatusTypeEnum;
use App\Models\NotificationMessageModel;
use App\Services\Notifications\Processors\NotificationMessageProcessor;
use App\Services\Notifications\RequestCounterRedisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Job is discarded when max attempts is exceeded. */
    public int $tries = 5;

    /** Seconds to wait between failed attempts. */
    public int $backoff = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $messageId)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $processor = app(NotificationMessageProcessor::class);
            $processor->handle($this->messageId);
        } catch (\Throwable $th) {
            $message = NotificationMessageModel::find($this->messageId);
            if ($message) {
                $message->update([
                    'status' => StatusTypeEnum::PENDING,
                    'delivery_state' => DeliveryStateEnum::QUEUED,
                    'last_error' => $th->getMessage(),
                ]);
            }
            Log::error("SEND_NOTIFICATION_MESSAGE_FAILED", [
                'message_id' => $this->messageId,
                'attempts' => $this->attempts(),
                'backoff' => $this->backoff,
                'exception' => $th->getMessage(),
            ]);
            $this->release($this->backoff);
        }
    }

    /**
     * Called when max attempts is exceeded. This is the only place that marks the message as FAILED; handler only logs and throws.
     */
    public function failed(\Throwable $e): void
    {
        $message = NotificationMessageModel::find($this->messageId);
        if ($message) {
            $message->update([
                'status' => StatusTypeEnum::FAILED,
                'delivery_state' => DeliveryStateEnum::FAILED,
                'last_error' => 'Max attempts exceeded: ' . $e->getMessage(),
            ]);

            $requestId = $message->request_id;
            if ($requestId) {
                app(RequestCounterRedisService::class)->incrementFailed($requestId);
            }
        }

        Log::warning('job_max_attempts', [
            'event' => 'job_max_attempts',
            'job' => static::class,
            'message_id' => $this->messageId,
            'attempts' => $this->attempts(),
            'reason' => class_basename($e),
        ]);
    }
}
