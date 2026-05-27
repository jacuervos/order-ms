<?php

use App\Models\Rol;
use App\Http\Controllers\CollectorController;
use App\Http\Controllers\CollectorOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth.jwt', 'role:'.Rol::USER])->group(function () {
    Route::post('orders/with-wastes', \App\Http\Controllers\OrderWithWastesStoreController::class); //crear orden desde usuario
});

Route::middleware(['auth.jwt', 'role:'.Rol::ADMIN])->group(function () {
    Route::get('orders/dashboard', \App\Http\Controllers\OrderIndexController::class.'@indexDashboard'); //Ver ordenes para el dashboard
    Route::get('orders/assign-collector', \App\Http\Controllers\OrderAssignedStoreController::class); //Asignar proveedor
});

Route::middleware('auth.jwt')->group(function () {
    Route::get('orders', \App\Http\Controllers\OrderIndexController::class);
    Route::post('orders', \App\Http\Controllers\OrderStoreController::class);
    Route::get('orders/{order}', \App\Http\Controllers\OrderShowController::class);
    Route::put('orders/{order}', \App\Http\Controllers\OrderUpdateController::class);
    Route::patch('orders/{order}', \App\Http\Controllers\OrderUpdateController::class);
    Route::delete('orders/{order}', \App\Http\Controllers\OrderDestroyController::class);
    Route::post('order-type-wastes', \App\Http\Controllers\OrderTypeWasteStoreController::class);
    Route::apiResource('collectors', CollectorController::class);
    Route::apiResource('collector-orders', CollectorOrderController::class);
});
