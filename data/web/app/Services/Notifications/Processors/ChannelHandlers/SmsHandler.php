<?php

namespace App\Services\Notifications\Processors\ChannelHandlers;

use App\Services\Notifications\Processors\ChannelHandlers\AbstractChannelHandler;
use Illuminate\Support\Facades\Log;

class SmsHandler extends AbstractChannelHandler
{
    protected function prepareData(array $notification): array
    {
        $templateSubject = $notification['request']['template_subject'];
        $templateBody = $notification['request']['template_body_inline'];
        $vars = $notification['message']['vars'];

        $subject = $this->renderTemplate($templateSubject, $vars);
        $content = $this->renderTemplate($templateBody, $vars);

        return [$subject, $content];
    }
}
