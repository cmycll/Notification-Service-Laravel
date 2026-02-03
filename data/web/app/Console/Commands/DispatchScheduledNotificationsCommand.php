<?php

namespace App\Console\Commands;

use App\Enums\StatusTypeEnum;
use App\Jobs\SendNotificationMessageJob;
use App\Models\NotificationRequestModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DispatchScheduledNotificationsCommand extends Command
{
    protected $signature = 'notifications:dispatch-scheduled';

    protected $description = 'Finds notification requests whose scheduled_at is due and dispatches their messages to the queue.';

    private const DISPATCH_RETRY_TIMES = 3;

    private const DISPATCH_RETRY_DELAY_MS = 200;

    public function handle(): int
    {
        try {
            $due = NotificationRequestModel::query()
                ->whereNotNull('scheduled_at')
                ->where('scheduled_at', '<=', now())
                ->where('status', StatusTypeEnum::PENDING)
                ->with('messages')
                ->get();

            $dispatchedRequests = 0;
            $dispatchedMessages = 0;

            /** @var NotificationRequestModel $request */
            foreach ($due as $request) {
                $request->update(['status' => StatusTypeEnum::PROCESSING]);

                $pendingMessages = $request->messages->filter(
                    fn ($message) => $message->status === StatusTypeEnum::PENDING
                );

                $priority = $request->priority instanceof \BackedEnum
                    ? $request->priority->value
                    : (string) $request->priority;

                foreach ($pendingMessages as $message) {
                    retry(self::DISPATCH_RETRY_TIMES, function () use ($message, $priority) {
                        SendNotificationMessageJob::dispatch($message->id)
                            ->onQueue($priority);
                    }, self::DISPATCH_RETRY_DELAY_MS);
                    $dispatchedMessages++;
                }

                $request->update(['scheduled_at' => null]);
                $dispatchedRequests++;
            }

            if ($this->output->isVerbose()) {
                $this->info("Dispatched {$dispatchedMessages} messages from {$dispatchedRequests} request(s).");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            report($e);
            Log::error('notifications:dispatch-scheduled failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
