<?php

use App\Models\Rol;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth.jwt', 'role:'.Rol::USER])->group(function () {
    Route::post('orders/with-wastes', \App\Http\Controllers\OrderWithWastesStoreController::class); //crear orden desde usuario
    Route::get('orders/my-orders', \App\Http\Controllers\OrderByUserController::class); //Ver mis órdenes como usuario
    Route::get('collector/location', \App\Http\Controllers\UserOrderTrackingController::class.'@getMyCollectorLocation'); //HU-18: Obtener ubicación del recolector
    Route::get('orders/active', \App\Http\Controllers\UserOrderTrackingController::class.'@getActiveOrders'); //Obtener órdenes activas con ubicación
    Route::get('orders/update-date-state', \App\Http\Controllers\OrderUpdateController::class.'@updateOrderUserReschedule'); //Cambiar fecha y estado
    Route::put('orders/update-date', \App\Http\Controllers\OrderUpdateController::class.'@updateOrderReschedule'); //Cambiar fecha
    Route::get('orders/reschedule', \App\Http\Controllers\UserOrderTrackingController::class.'@getRescheduleOrders'); //Obtener órdenes reprogramadas
});

Route::middleware(['auth.jwt', 'role:'.Rol::RECYCLER])->group(function () {
    Route::get('orders/my-collections', \App\Http\Controllers\OrderByCollectorController::class); //Ver órdenes asignadas al recolector
    Route::post('orders/{order}/accept', \App\Http\Controllers\CollectorAcceptOrderController::class); //Aceptar orden e iniciar servicio
    Route::get('pickup-points', \App\Http\Controllers\CollectorPickupPointsController::class.'@getMyAssignedPickupPoints'); //HU-19: Obtener puntos de recogida asignados
    Route::put('orders/{order}/picked-up', \App\Http\Controllers\CollectorPickupPointsController::class.'@markPickupPointAsPicked'); //Marcar orden como recogida
    Route::put('orders/{order}/complete', \App\Http\Controllers\CollectorPickupPointsController::class.'@completePickupPoint'); //Marcar orden como completada
    Route::get('orders/{order}/details', \App\Http\Controllers\CollectorPickupPointsController::class.'@getOrderDetails'); //Detalles de orden
    Route::post('collector/location', \App\Http\Controllers\UpdateCollectorLocationController::class); //HU-18: Actualizar ubicación del recolector
    Route::put('orders/update-state', \App\Http\Controllers\OrderUpdateController::class.'@updateOrderRecolectorReschedule'); //Cambiar estado de orden
});

Route::middleware(['auth.jwt', 'role:'.Rol::ADMIN])->group(function () {
    Route::get('orders/dashboard', \App\Http\Controllers\OrderIndexController::class.'@indexDashboard'); //Ver ordenes para el dashboard
    Route::get('orders', \App\Http\Controllers\OrderIndexController::class.'@index'); //Ver ordenes para el backoffice
    Route::post('orders/assign-collector', \App\Http\Controllers\OrderAssignedStoreController::class); //Asignar recolector
    Route::post('orders/init', \App\Http\Controllers\OrderInitStoreController::class); // Iniciar ordenes
    Route::post('orders/cancel', \App\Http\Controllers\OrderCancelController::class); // Cancelar orden
    Route::post('orders/finish', \App\Http\Controllers\OrderFinishController::class); // Finalizar orden
    Route::get('all_orders', \App\Http\Controllers\DashboardsController::class.'@allOrders'); //Ver órdenes para el dashboard
    Route::get('total_users', \App\Http\Controllers\DashboardsController::class.'@listTotalUsers'); //Ver total de usuarios por ordenes
    Route::get('last_date_users', \App\Http\Controllers\DashboardsController::class.'@listDateUsers'); //Ver últimas ordenes por usuario
    Route::get('total_collectors', \App\Http\Controllers\DashboardsController::class.'@listTopCollectors'); //Ver total de recolectores por ordenes
});

Route::middleware('auth.jwt')->group(function () {
    Route::post('orders', \App\Http\Controllers\OrderStoreController::class);
    Route::get('orders/{order}', \App\Http\Controllers\OrderShowController::class);
    Route::put('orders/{order}', \App\Http\Controllers\OrderUpdateController::class);
    Route::patch('orders/{order}', \App\Http\Controllers\OrderUpdateController::class);
    Route::delete('orders/{order}', \App\Http\Controllers\OrderDestroyController::class);
    Route::post('order-type-wastes', \App\Http\Controllers\OrderTypeWasteStoreController::class);
});
