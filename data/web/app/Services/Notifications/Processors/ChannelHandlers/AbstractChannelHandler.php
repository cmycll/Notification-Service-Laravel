<?php

namespace App\Services\Notifications\Processors\ChannelHandlers;

use App\Enums\DeliveryStateEnum;
use App\Models\NotificationMessageModel;
use App\Enums\StatusTypeEnum;
use App\Services\Notifications\RequestCounterRedisService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abstract class AbstractChannelHandler
{
    abstract protected function prepareData(array $notification): array;

    public function handle(array $notification): void
    {
        $to = $notification['message']['to'];
        $channel = $notification['message']['channel'];
        [$subject, $content] = $this->prepareData($notification);

        /** @var \Illuminate\Http\Client\Response $httpResponse */
        $httpResponse = Http::withHeaders(['Accept' => 'application/json'])
            ->post(env('NOTIF_SERVICE_URL'), [
            'to' => $to,
            'channel' => $channel,
            'subject' => $subject,
            'content' => $content,
        ]);
        $response = $httpResponse->json();

        // thorw exception if http code note 202
        if ($httpResponse->status() !== 202) {
            throw new \Exception("Failed to send notification message: " . $httpResponse->body());
        }

        // there is no return provider message id from the provider, so we generate a mock random one
        $providerMessageId = Str::uuid7();
        ;
        $timestamp = now()->toIso8601String();

        $notificationMessage = NotificationMessageModel::where('id', $notification['message']['id'])->first();
        $notificationMessage->update([
            'status' => StatusTypeEnum::SENT,
            'delivery_state' => $this->providerResponseStatusMap($response['status']),
            'provider_message_id' => $providerMessageId,
            'timestamp' => $timestamp,
        ]);

        $requestId = $notification['request']['id'];
        if ($requestId) {
            app(RequestCounterRedisService::class)->incrementSent($requestId);
        }

        Log::info("NOTIFICATION_MESSAGE_SENT", [
            'message_id' => $notification['message']['id'],
            'channel' => $channel,
        ]);
    }

    protected function providerResponseStatusMap(string $status): DeliveryStateEnum
    {
        return match ($status) {
            'accepted' => DeliveryStateEnum::QUEUED,
            'error' => DeliveryStateEnum::FAILED,
        };
    }

    protected function renderTemplate(string $template, array $vars)
    {
        return preg_replace_callback('/{{(.*?)}}/', function ($matches) use ($vars) {
            $key = trim($matches[1]);
            return htmlspecialchars((string) $vars[$key]);
        }, $template);
    }

    /**
     * Storage::disk('local')->get() expects a path relative to the disk root.
     * If the DB stores an absolute path (/var/www/html/storage/app/private/...), converts it to relative.
     */
    protected function normalizeStoragePath(string $path): string
    {
        $root = rtrim(Storage::disk('local')->path(''), '/');
        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, $root)) {
            return ltrim(substr($path, \strlen($root)), '/');
        }
        return $path;
    }
}
