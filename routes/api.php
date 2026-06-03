<?php

use App\Models\Rol;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth.jwt', 'role:'.Rol::USER])->group(function () {
    Route::post('orders/with-wastes', \App\Http\Controllers\OrderWithWastesStoreController::class); //crear orden desde usuario
});

Route::middleware(['auth.jwt', 'role:'.Rol::ADMIN])->group(function () {
    Route::get('orders/dashboard', \App\Http\Controllers\OrderIndexController::class.'@indexDashboard'); //Ver ordenes para el dashboard
    Route::get('orders', \App\Http\Controllers\OrderIndexController::class.'@index'); //Ver ordenes para el backoffice
    Route::post('orders/assign-collector', \App\Http\Controllers\OrderAssignedStoreController::class); //Asignar recolector
    Route::post('orders/init', \App\Http\Controllers\OrderInitStoreController::class); // Iniciar ordenes
    Route::post('orders/cancel', \App\Http\Controllers\OrderCancelController::class); // Cancelar orden
});

Route::middleware('auth.jwt')->group(function () {
    Route::post('orders', \App\Http\Controllers\OrderStoreController::class);
    Route::get('orders/{order}', \App\Http\Controllers\OrderShowController::class);
    Route::put('orders/{order}', \App\Http\Controllers\OrderUpdateController::class);
    Route::patch('orders/{order}', \App\Http\Controllers\OrderUpdateController::class);
    Route::delete('orders/{order}', \App\Http\Controllers\OrderDestroyController::class);
    Route::post('order-type-wastes', \App\Http\Controllers\OrderTypeWasteStoreController::class);
});
