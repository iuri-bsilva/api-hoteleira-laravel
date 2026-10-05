<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RoomCategoryController;
use App\Http\Controllers\RoomController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:api-login');
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('hotels', [HotelController::class, 'index']);
    Route::get('hotels/{hotel}/categories', [RoomCategoryController::class, 'index']);
    Route::post('hotels/{hotel}/categories', [RoomCategoryController::class, 'store']);
    Route::get('hotels/{hotel}/categories/availability', [RoomCategoryController::class, 'availability']);
    Route::get('hotels/{hotel}/coupons', [CouponController::class, 'index']);
    Route::post('hotels/{hotel}/coupons', [CouponController::class, 'store']);
    Route::patch('hotels/{hotel}/coupons/{coupon}', [CouponController::class, 'update']);
    Route::get('rooms/availability', [RoomController::class, 'availability']);
    Route::apiResource('rooms', RoomController::class);
    Route::apiResource('reservations', ReservationController::class)->only(['index', 'show', 'store']);
    Route::get('reservations/{reservation}/payments', [PaymentController::class, 'index']);
    Route::post('reservations/{reservation}/payments', [PaymentController::class, 'store']);
});
