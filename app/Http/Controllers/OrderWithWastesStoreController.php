<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderTypeWaste;
use App\Http\Requests\OrderWithWastesStoreRequest;
use App\Models\State;
use Illuminate\Support\Facades\DB;

class OrderWithWastesStoreController extends Controller
{
    public function __invoke(OrderWithWastesStoreRequest $request)
    {
        DB::beginTransaction();

        try {
            $order = Order::create([
                'user_id'   => $request->attributes->get('user_id'),
                'latitude'  => $request->latitude,
                'longitude' => $request->longitude,
                'date'      => $request->date,
                'state_id'  => State::where('name', State::ORDER_CREATION)->value('id'),
            ]);

            foreach ($request->items as $item) {
                OrderTypeWaste::create([
                    'order_id'      => $order->id,
                    'type_waste_id' => $item['type_waste_id'],
                    'weight'        => $item['weight'],
                    'points'        => $item['points'],
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Orden creada correctamente',
                'data'    => $order->load('orderTypeWastes'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al crear la orden: ' . $e->getMessage(),
            ], 500);
        }
    }
}
