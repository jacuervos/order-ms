<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderInitStoreRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderTypeWaste;
use App\Models\State;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;

class OrderCancelController extends Controller
{
    public function __invoke(OrderInitStoreRequest $request)
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

            $orders = Order::where('id', $validated['order_id'])->get();

            OrderTypeWaste::where('order_id', $validated['order_id'])
                ->update([
                    'points' => 0,
            ]);
            DB::commit();

            $token = $request->bearerToken();

            $orders = $orders->map(function ($order) use ($orderService, $token) {
                return $orderService->enrichOrder($order, $token);
            });

            return response()->json([
                'success' => true,
                'message' => 'Órdenes cancelada correctamente',
                'data'    => OrderResource::collection($orders),
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
