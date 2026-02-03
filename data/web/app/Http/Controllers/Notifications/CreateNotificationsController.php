<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Services\Notifications\Operations\SendNotificationService;
use Illuminate\Http\Request;
use App\Services\Notifications\RequestService;
use Illuminate\Support\Facades\Log;
use App\Services\Notifications\DTO\NotificationRequest;
use Illuminate\Validation\ValidationException;

class CreateNotificationsController extends Controller
{
    public function __construct(
        protected SendNotificationService $sendNotificationService,
        protected RequestService $requestService
    ) {
    }

    public function __invoke(Request $request)
    {
        try {
            $validated = $this->requestService->validateRequestData($request);

            if (!empty($validated['idempotency_key'])) {
                $existingRequest = $this->requestService->getRequestByIdempotencyKey($validated['idempotency_key']);
                if (!empty($existingRequest)) {
                    return response()->json([
                        'success' => true,
                        'request_id' => $existingRequest['id'],
                        'requested_count' => $existingRequest['requested_count'],
                        'accepted_count' => $existingRequest['accepted_count'],
                        'rejected_count' => $existingRequest['requested_count'] - $existingRequest['accepted_count'],
                        'pending_count' => $existingRequest['panding_count'],
                    ]);
                }
            }

            $notificationRequest = new NotificationRequest(
                $validated['template'],
                $validated['recipients'],
                $validated['channel'],
                $validated['priority'],
                $validated['idempotency_key'] ?? null,
                $validated['scheduled_at'] ?? null,
                $validated['requested_count'],
                $validated['accepted_count'],
                $validated['rejected_count'],
            );

            $requestId = $this->requestService->createRequestRecord($notificationRequest);

            if (empty($validated['scheduled_at'])) {
                $this->sendNotificationService->addToQueue($requestId);
            }

            return response()->json([
                'success' => true,
                'request_id' => $requestId,
                'requested_count' => $validated['requested_count'],
                'accepted_count' => $validated['accepted_count'],
                'rejected_count' => $validated['rejected_count'],
                'pending_count' => $validated['accepted_count'],
            ]);
        } catch (\Throwable $th) {
            if ($th instanceof ValidationException) {
                throw $th;
            }
            Log::error("CREATE_NOTIFICATION_REQUEST_FAILED", [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
