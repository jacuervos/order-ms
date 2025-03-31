<?php
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CollectorController;
use App\Http\Controllers\CollectorOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt')->group(function () {
    Route::apiResource('orders', OrderController::class);
    Route::apiResource('collectors', CollectorController::class);
    Route::apiResource('collector-orders', CollectorOrderController::class);
});
