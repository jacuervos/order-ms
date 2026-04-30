<?php
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CollectorController;
use App\Http\Controllers\CollectorOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt')->group(function () {
    Route::get('orders', \App\Http\Controllers\OrderIndexController::class);
    Route::post('orders', \App\Http\Controllers\OrderStoreController::class);
    Route::get('orders/{order}', \App\Http\Controllers\OrderShowController::class);
    Route::put('orders/{order}', \App\Http\Controllers\OrderUpdateController::class);
    Route::patch('orders/{order}', \App\Http\Controllers\OrderUpdateController::class);
    Route::delete('orders/{order}', \App\Http\Controllers\OrderDestroyController::class);
    Route::apiResource('collectors', CollectorController::class);
    Route::apiResource('collector-orders', CollectorOrderController::class);
});
