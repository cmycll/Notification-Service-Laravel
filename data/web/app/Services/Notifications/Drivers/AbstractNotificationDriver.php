<?php

namespace App\Services\Notifications\Drivers;

abstract class AbstractNotificationDriver
{
    // abstract public function prepareTemplateData(array $data): array;
    public function prepareTemplateData(array $template): array
    {
        $template['body_path'] = '';

        return $template;
    }

    /**
     * Validate channel-specific template limits.
     */
    public function validateTemplateLimits(array $template): void
    {
        // Default: no additional limits.
    }
}
