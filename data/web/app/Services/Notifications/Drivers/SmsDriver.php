<?php

namespace App\Services\Notifications\Drivers;

use Illuminate\Validation\ValidationException;

class SmsDriver extends AbstractNotificationDriver
{
    public function validateTemplateLimits(array $template): void
    {
        $body = (string) ($template['body'] ?? '');
        if (mb_strlen($body) > 160) {
            throw ValidationException::withMessages([
                'template.body' => ['Body exceeds 160 characters for sms.'],
            ]);
        }
    }
}
