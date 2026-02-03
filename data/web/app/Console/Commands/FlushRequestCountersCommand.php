<?php

namespace App\Console\Commands;

use App\Services\Notifications\RequestCounterRedisService;
use Illuminate\Console\Command;

class FlushRequestCountersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'request-counters:flush';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flushes Redis (db 3) request counters to MySQL notif_requests table. Run periodically via Scheduler.';

    /**
     * Execute the console command.
     */
    public function handle(RequestCounterRedisService $service): int
    {
        $result = $service->flushToMysql();

        if ($this->output->isVerbose()) {
            $this->info("Flushed: {$result['flushed']}, Errors: {$result['errors']}");
        }

        return $result['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
