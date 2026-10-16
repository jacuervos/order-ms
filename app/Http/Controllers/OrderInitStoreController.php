<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderInitStoreRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\State;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;

class OrderInitStoreController extends Controller
{
    public function __invoke(OrderInitStoreRequest $request)
    {
        DB::beginTransaction();

        $orderService = new OrderService();

        try {
            $validated = $request->validated();

            $stateId = State::where('name', State::SERVICE_START)->value('id');

            Order::whereIn('id', $validated['order_ids'])
                ->update([
                    'state_id' => $stateId,
                ]);

            $orders = Order::whereIn('id', $validated['order_ids'])->get();

            DB::commit();

            $token = $request->bearerToken();

            $orders = $orders->map(function ($order) use ($orderService, $token) {
                return $orderService->enrichOrder($order, $token ?? '');
            });

            return response()->json([
                'success' => true,
                'message' => 'Órdenes iniciadas correctamente',
                'data'    => OrderResource::collection($orders),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar órdenes: ' . $e->getMessage(),
            ], 500);
        }
    }
}
