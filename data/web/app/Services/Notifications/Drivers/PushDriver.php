<?php

namespace App\Services\Notifications\Drivers;

use Illuminate\Validation\ValidationException;

class PushDriver extends AbstractNotificationDriver
{
    public function validateTemplateLimits(array $template): void
    {
        $subject = (string) ($template['subject'] ?? '');
        $body = (string) ($template['body'] ?? '');

        $errors = [];
        if (mb_strlen($subject) > 100) {
            $errors['template.subject'][] = 'Subject exceeds 100 characters for push.';
        }
        if (mb_strlen($body) > 200) {
            $errors['template.body'][] = 'Body exceeds 200 characters for push.';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}
