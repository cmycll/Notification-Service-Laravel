<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\NotificationMessageModel;
use App\Models\NotificationRequestModel;
use App\Enums\StatusTypeEnum;
use App\Enums\DeliveryStateEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CancelNotificationController extends Controller
{
    public function cancelMessage(Request $request, string $id)
    {
        return DB::transaction(function () use ($id) {
            $message = NotificationMessageModel::query()
                ->where('id', $id)
                ->whereHas('request', function ($subQuery) {
                    $subQuery->where('client_id', Auth::id());
                })
                ->lockForUpdate()
                ->first();

            if (!$message) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found',
                ], 404);
            }

            if ($message->status !== StatusTypeEnum::PENDING) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending notifications can be cancelled',
                ], 409);
            }

            $message->update([
                'status' => StatusTypeEnum::CANCELLED,
                'delivery_state' => DeliveryStateEnum::REJECTED,
            ]);

            NotificationRequestModel::where('id', $message->request_id)->update([
                'pending_count' => DB::raw('CASE WHEN pending_count - 1 < 0 THEN 0 ELSE pending_count - 1 END'),
                'cancelled_count' => DB::raw('cancelled_count + 1'),
            ]);

            $request = NotificationRequestModel::where('id', $message->request_id)->lockForUpdate()->first();

            if ($request && $request->pending_count <= 0) {
                $request->update(['status' => StatusTypeEnum::CANCELLED]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $message->id,
                    'request_id' => $message->request_id,
                    'status' => $message->status?->value,
                    'delivery_state' => $message->delivery_state?->value,
                ],
            ]);
        });
    }

    public function cancelRequest(Request $request, string $id)
    {
        return DB::transaction(function () use ($id) {
            $requestModel = NotificationRequestModel::query()
                ->where('id', $id)
                ->where('client_id', Auth::id())
                ->lockForUpdate()
                ->first();

            if (!$requestModel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Request not found',
                ], 404);
            }

            $pendingCount = NotificationMessageModel::query()
                ->where('request_id', $requestModel->id)
                ->where('status', StatusTypeEnum::PENDING)
                ->count();

            if ($pendingCount === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No pending notifications to cancel',
                ], 409);
            }

            NotificationMessageModel::query()
                ->where('request_id', $requestModel->id)
                ->where('status', StatusTypeEnum::PENDING)
                ->update([
                    'status' => StatusTypeEnum::CANCELLED,
                    'delivery_state' => DeliveryStateEnum::REJECTED,
                ]);

            $requestModel->update([
                'pending_count' => DB::raw('CASE WHEN pending_count - ' . $pendingCount . ' < 0 THEN 0 ELSE pending_count - ' . $pendingCount . ' END'),
                'cancelled_count' => DB::raw('cancelled_count + ' . $pendingCount),
            ]);

            $requestModel->refresh();

            if ($requestModel->pending_count <= 0) {
                $requestModel->update(['status' => StatusTypeEnum::CANCELLED]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'request_id' => $requestModel->id,
                    'cancelled_count' => $pendingCount,
                    'pending_count' => $requestModel->pending_count,
                    'status' => $requestModel->status?->value,
                ],
            ]);
        });
    }
}
