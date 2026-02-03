<?php

namespace App\Services\Notifications\Processors;

use App\Services\Notifications\Processors\ChannelHandlers\{
    SmsHandler, PushHandler, EmailHandler, AbstractChannelHandler
};

class ChannelHandlerFactory
{
    public function make(string $channel): AbstractChannelHandler
    {
        return match ($channel) {
            'sms' => app(SmsHandler::class),
            'push' => app(PushHandler::class),
            'email' => app(EmailHandler::class),
            default => throw new \RuntimeException("Unsupported channel: {$channel}"),
        };
    }
}
