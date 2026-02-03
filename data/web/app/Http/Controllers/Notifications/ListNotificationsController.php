<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\NotificationRequestModel;
use App\Models\NotificationMessageModel;
use App\Enums\StatusTypeEnum;
use App\Enums\ChannelTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ListNotificationsController extends Controller
{
    public function listNotifications(Request $request)
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(array_map(fn ($case) => $case->value, StatusTypeEnum::cases()))],
            'channel' => ['sometimes', 'string', Rule::in(array_map(fn ($case) => $case->value, ChannelTypeEnum::cases()))],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = NotificationRequestModel::query()
            ->where('client_id', Auth::id())
            ->orderByDesc('created_at');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['channel'])) {
            $query->where('channel', $validated['channel']);
        }

        if (!empty($validated['from'])) {
            $query->where('created_at', '>=', $validated['from']);
        }

        if (!empty($validated['to'])) {
            $query->where('created_at', '<=', $validated['to']);
        }

        $perPage = $validated['per_page'] ?? 20;
        $paginator = $query->paginate($perPage);

        $data = $paginator->getCollection()->map(function (NotificationRequestModel $requestModel) {
            return [
                'request_id' => $requestModel->id,
                'status' => $requestModel->status?->value,
                'channel' => $requestModel->channel?->value,
                'priority' => $requestModel->priority?->value,
                'requested_count' => $requestModel->requested_count,
                'accepted_count' => $requestModel->accepted_count,
                'pending_count' => $requestModel->pending_count,
                'sent_count' => $requestModel->sent_count,
                'failed_count' => $requestModel->failed_count,
                'cancelled_count' => $requestModel->cancelled_count,
                'scheduled_at' => $requestModel->scheduled_at,
                'created_at' => $requestModel->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function listRequestMessages(Request $request, string $id)
    {
        $requestModel = NotificationRequestModel::query()
            ->with('messages')
            ->where('id', $id)
            ->where('client_id', Auth::id())
            ->first();

        if (!$requestModel) {
            return response()->json([
                'success' => false,
                'message' => 'Request not found',
            ], 404);
        }

        $messages = $requestModel->messages->map(function (NotificationMessageModel $message) {
            return [
                'id' => $message->id,
                'to' => $message->to,
                'channel' => $message->channel,
                'priority' => $message->priority,
                'status' => $message->status?->value,
                'delivery_state' => $message->delivery_state?->value,
                'attempts' => $message->attempts,
                'provider_message_id' => $message->provider_message_id,
                'last_error' => $message->last_error,
                'created_at' => $message->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'request' => [
                    'id' => $requestModel->id,
                    'status' => $requestModel->status?->value,
                    'requested_count' => $requestModel->requested_count,
                    'accepted_count' => $requestModel->accepted_count,
                    'pending_count' => $requestModel->pending_count,
                    'sent_count' => $requestModel->sent_count,
                    'failed_count' => $requestModel->failed_count,
                    'cancelled_count' => $requestModel->cancelled_count,
                    'channel' => $requestModel->channel?->value,
                    'priority' => $requestModel->priority?->value,
                    'scheduled_at' => $requestModel->scheduled_at,
                    'created_at' => $requestModel->created_at,
                ],
                'messages' => $messages,
            ],
        ]);
    }
}
