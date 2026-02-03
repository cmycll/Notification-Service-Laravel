<?php

namespace App\Services\Notifications\Processors\ChannelHandlers;

use App\Enums\ChannelTypeEnum;
use App\Models\NotificationMessageModel;
use App\Enums\StatusTypeEnum;
use App\Services\Notifications\Processors\ChannelHandlers\AbstractChannelHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class PushHandler extends AbstractChannelHandler
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
