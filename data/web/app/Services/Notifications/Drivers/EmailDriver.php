<?php

namespace App\Services\Notifications\Drivers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EmailDriver extends AbstractNotificationDriver
{
    public function validateTemplateLimits(array $template): void
    {
        $subject = (string) ($template['subject'] ?? '');
        $body = (string) ($template['body'] ?? '');

        $errors = [];
        if (mb_strlen($subject) > 255) {
            $errors['template.subject'][] = 'Subject exceeds 255 characters for email.';
        }
        if (mb_strlen($body) > 10000) {
            $errors['template.body'][] = 'Body exceeds 10000 characters for email.';
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function prepareTemplateData(array $template): array
    {
        $content = $template['body'];

        // save the content to storage (disk root: storage/app/private)
        $fileName = 'email_' . request()->header('X-Correlation-ID') . '.txt';
        $relativePath = "notifications/{$fileName}";
        Storage::disk('local')->put($relativePath, $content);

        $template['body_path'] = $relativePath;
        $template['body'] = '';

        return $template;
    }
}
