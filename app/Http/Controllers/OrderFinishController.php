<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderFinishStoreRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderTypeWaste;
use App\Models\State;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;

class OrderFinishController extends Controller
{
    public function __invoke(OrderFinishStoreRequest $request)
    {
        DB::beginTransaction();

        $orderService = new OrderService();

        try {
            $validated = $request->validated();

            $stateId = State::where('name', State::ORDER_COMPLETED)->value('id');

            Order::where('id', $validated['order_id'])
                ->update([
                    'state_id' => $stateId,
                ]);

            $order = Order::findOrFail($validated['order_id']);

            foreach ($request->items as $item) {
                OrderTypeWaste::where('order_id', $order->id)
                    ->where('type_waste_id', $item['type_waste_id'])
                    ->update([
                        'weight' => $item['weight'],
                        'points' => $item['points'],
                    ]);
            }

            DB::commit();

            $token = $request->bearerToken();

            $order = $orderService->enrichOrder($order, $token);

            return response()->json([
                'success' => true,
                'message' => 'Órdenes finalizada correctamente',
                'data'    => new OrderResource($order),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al finalizar la órden: ' . $e->getMessage(),
            ], 500);
        }
    }
}
