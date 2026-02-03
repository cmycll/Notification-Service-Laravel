<?php

namespace App\Services\Notifications\Processors\ChannelHandlers;

use App\Enums\ChannelTypeEnum;
use App\Enums\StatusTypeEnum;
use App\Models\NotificationMessageModel;
use App\Services\Notifications\Processors\ChannelHandlers\AbstractChannelHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class EmailHandler extends AbstractChannelHandler
{
    protected function prepareData(array $notification): array
    {
        $templateSubject = $notification['request']['template_subject'];
        $templateBodyPath = $this->normalizeStoragePath($notification['request']['template_body_path']);
        $templateBody = Storage::disk('local')->get($templateBodyPath);
        $vars = $notification['message']['vars'];

        $subject = $this->renderTemplate($templateSubject, $vars);
        $content = $this->renderTemplate($templateBody, $vars);

        return [$subject, $content];
    }
}
