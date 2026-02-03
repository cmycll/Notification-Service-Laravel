<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Http\Controllers\Notifications\CreateNotificationsController;
use App\Http\Controllers\Notifications\ListNotificationsController;
use App\Http\Controllers\Notifications\CancelNotificationController;
use App\Http\Controllers\System\HealthController;
use App\Http\Controllers\System\MetricsController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/user', function () {
    $data = request()->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:8',
    ]);

    $user = User::create([
        'name' => $data['name'],
        'email' => $data['email'],
        'password' => $data['password'],
    ]);

    // Create token following Sanctum architecture
    $token = $user->createToken('default-api-token')->plainTextToken;

    return response()->json([
        'message' => 'User created successfully',
        'user' => $user,
        'api_token' => $token,
    ], 201);
})->name('createUser');

Route::prefix('notifications')->middleware('auth:sanctum')->group(function () {
    Route::post('/', CreateNotificationsController::class);           // single + batch
    Route::get('/', [ListNotificationsController::class, 'listNotifications']);               // filter + pagination
    Route::get('{id}', [ListNotificationsController::class, 'listRequestMessages']);
    Route::post('message/{id}/cancel', [CancelNotificationController::class, 'cancelMessage']);
    Route::post('request/{id}/cancel', [CancelNotificationController::class, 'cancelRequest']);
});

Route::prefix('system')->middleware('auth:sanctum')->group(function () {
    Route::get('/health', HealthController::class);
    Route::get('/metrics', MetricsController::class);
});
