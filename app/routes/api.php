<?php

use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RoomController;
use App\Models\Hotel;
use Illuminate\Support\Facades\Route;

Route::get('hotels', fn () => Hotel::paginate(20));
Route::apiResource('rooms', RoomController::class);
Route::apiResource('reservations', ReservationController::class)->only(['index', 'show', 'store']);
