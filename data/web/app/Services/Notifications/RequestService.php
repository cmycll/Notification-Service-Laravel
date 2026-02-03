<?php

namespace App\Services\Notifications;

use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Enums\ChannelTypeEnum;
use App\Enums\DeliveryStateEnum;
use App\Enums\PriorityTypeEnum;
use App\Enums\StatusTypeEnum;
use App\Models\NotificationMessageModel;
use App\Models\NotificationRequestModel;
use App\Services\Notifications\DTO\NotificationRequest;
use App\Services\Notifications\Drivers\AbstractNotificationDriver;
use App\Services\Notifications\Drivers\EmailDriver;
use App\Services\Notifications\Drivers\PushDriver;
use App\Services\Notifications\Drivers\SmsDriver;

class RequestService
{
    protected $drivers = [
        'email' => EmailDriver::class,
        'sms'   => SmsDriver::class,
        'push'  => PushDriver::class,
    ];

    /**
     * Create a request record and message records
     * @param NotificationRequest $notificationRequest
     * @return string
     * @throws Exception
     */
    public function createRequestRecord(NotificationRequest $notificationRequest): string
    {
        try {
            return DB::transaction(function () use ($notificationRequest) {
                $correlationId = request()->header('X-Correlation-ID');
                $clientId = Auth::id();
                $requestId = Str::uuid7();

                $idempotencyKey = $notificationRequest->idempotencyKey ?: null;

                NotificationRequestModel::create([
                    'id' => $requestId,
                    'client_id' => $clientId,
                    'idempotency_key' => $idempotencyKey,
                    'correlation_id' => $correlationId,
                    'template_subject' => $notificationRequest->template['subject'],
                    'template_body_path' => $notificationRequest->template['body_path'] ?? '',
                    'template_body_inline' => $notificationRequest->template['body'] ?? '',
                    'requested_count' => $notificationRequest->requestedCount,
                    'accepted_count' => $notificationRequest->acceptedCount,
                    'pending_count' => $notificationRequest->acceptedCount,
                    'channel' => $notificationRequest->channel,
                    'priority' => $notificationRequest->priority,
                    'status' => StatusTypeEnum::PENDING,
                    'scheduled_at' => $notificationRequest->scheduledAt
                        ? Carbon::parse($notificationRequest->scheduledAt)->utc()
                        : null,
                ]);

                // Batch insert (chunked) to avoid N inserts for N recipients.
                // Note: insert() bypasses Eloquent casts, so we store enum values and JSON ourselves.
                $now = now();
                $rows = [];

                foreach ($notificationRequest->recipients as $recipient) {
                    $rows[] = [
                        'id' => (string) Str::uuid7(),
                        'request_id' => $requestId,
                        'to' => $recipient['to'],
                        'vars' => json_encode($recipient['vars'] ?? [], JSON_THROW_ON_ERROR),
                        'channel' => $notificationRequest->channel,
                        'priority' => $notificationRequest->priority,
                        'status' => StatusTypeEnum::PENDING->value,
                        'delivery_state' => DeliveryStateEnum::QUEUED->value,
                        'attempts' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    NotificationMessageModel::query()->insert($chunk);
                }

                return $requestId;
            });
        } catch (\Throwable $th) {
            throw new Exception("Failed to create request record: {$th->getMessage()}");
        }
    }

    /**
     * Validate request data and return validated data
     * @param Request $request
     * @return array
     */
    public function validateRequestData(Request $request): array
    {
        $validated = $request->validate([
            'channel' => ['required', Rule::in(ChannelTypeEnum::cases())],
            'priority' => ['required', Rule::in(PriorityTypeEnum::cases())],
            'template' => ['required', 'array'],
            'template.subject' => ['required', 'string', 'max:255'],
            'template.body' => ['required', 'string'],
            'recipients' => ['required', 'array', 'min:1', 'max:1000'],
            'recipients.*.to' => ['required', 'string'],
            'recipients.*.vars' => ['nullable', 'array'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'scheduled_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ]);

        $receivedRecipientsCount = \count($validated['recipients']);

        $templateVars = $this->getTemplateVars($validated['template']);
        $validated['recipients'] = $this->validateRecipients($templateVars, $validated['recipients']);

        $acceptedRecipientsCount = \count($validated['recipients']);

        $validated['requested_count'] = $receivedRecipientsCount;
        $validated['accepted_count'] = $acceptedRecipientsCount;
        $validated['rejected_count'] = $receivedRecipientsCount - $acceptedRecipientsCount;

        /** @var AbstractNotificationDriver $driver */
        $driver = app($this->drivers[$validated['channel']]);
        $driver->validateTemplateLimits($validated['template']);
        $validated['template'] = $driver->prepareTemplateData($validated['template']);

        $validated['idempotency_key'] ??= "i-" . Str::random(32);

        return $validated;
    }


    /**
     * Validate recipients by template variables
     * @param array $templateVars
     * @param array $recipients
     * @return array
     */
    private function validateRecipients(array $templateVars, array $recipients): array
    {
        $recipients = array_filter($recipients, function ($recipient) use ($templateVars) {
            $missingVars = !empty($templateVars) ? array_diff($templateVars, array_keys($recipient['vars'] ?? [])) : [];
            $isAllScalar = \count(array_filter($recipient['vars'] ?? [], 'is_scalar')) === \count($recipient['vars'] ?? []);
            return empty($missingVars) && !empty($recipient['to']) && $isAllScalar;
        });

        return $recipients;
    }

    /**
     * Get template variables
     * @param array $template
     * @return array
     */
    private function getTemplateVars(array $template): array
    {
        $requiredVars = [];

        foreach (['subject', 'body'] as $key) {
            preg_match_all('/\{\{\s*(.*?)\s*\}\}/', $template[$key] ?? '', $matches);
            $requiredVars = [...$requiredVars, ...array_unique($matches[1])];
        }

        return $requiredVars;
    }

    /**
     * Get request by idempotency key
     * @param string $idempotencyKey
     * @return array|null
     */
    public function getRequestByIdempotencyKey(string $idempotencyKey): ?array
    {
        $request = NotificationRequestModel::where('idempotency_key', $idempotencyKey)->first();
        if (!$request) {
            return null;
        }

        return $request->toArray();
    }
}
