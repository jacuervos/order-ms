<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderCancelStoreRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderTypeWaste;
use App\Models\State;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;

class OrderCancelController extends Controller
{
    public function __invoke(OrderCancelStoreRequest $request)
    {
        DB::beginTransaction();

        $orderService = new OrderService();

        try {
            $validated = $request->validated();

            $stateId = State::where('name', State::CANCEL)->value('id');

            Order::where('id', $validated['order_id'])
                ->update([
                    'state_id' => $stateId,
                ]);

            $order = Order::findOrFail($validated['order_id']);

            OrderTypeWaste::where('order_id', $validated['order_id'])
                ->update([
                    'points' => 0,
            ]);
            DB::commit();

            $token = $request->bearerToken();

            $order = $orderService->enrichOrder($order, $token);

            return response()->json([
                'success' => true,
                'message' => 'Órdenes cancelada correctamente',
                'data'    => new OrderResource($order),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar órden: ' . $e->getMessage(),
            ], 500);
        }
    }
}
