<?php

namespace App\Services\Notifications\DTO;

class NotificationRequest
{
    public function __construct(
        public array $template,
        public array $recipients,
        public string $channel,
        public string $priority,
        public ?string $idempotencyKey = null,
        public ?string $scheduledAt = null,
        public int $requestedCount,
        public int $acceptedCount,
        public int $rejectedCount,
    ) {
    }
}
