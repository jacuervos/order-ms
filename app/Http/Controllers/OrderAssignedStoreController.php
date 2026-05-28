<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\CollectorOrder;
use App\Models\Order;
use App\Http\Requests\OrderAssignedStoreRequest;
use App\Models\State;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;

class OrderAssignedStoreController extends Controller
{
    public function __invoke(OrderAssignedStoreRequest $request)
    {
        DB::beginTransaction();

        $orderService = new OrderService();

        try {
            $validated = $request->validated();
            $order = Order::findOrFail($validated['order_id']);
            CollectorOrder::create([
                'order_id'   => $request->order_id,
                'collector_id'  => $request->collector_id,
            ]);
            $stateId = State::where('name', State::ASSIGNED_TO_COLLECTOR)->value('id');
            $order->update([
                'state_id' => $stateId,
            ]);

            DB::commit();

            $token = $request->bearerToken();

            $order = $orderService->enrichOrder($order, $token);

            return response()->json([
                'success' => true,
                'message' => 'Recolector asignado correctamente',
                'data'    => new OrderResource($order),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al asignar el recolector: ' . $e->getMessage(),
            ], 500);
        }
    }
}
