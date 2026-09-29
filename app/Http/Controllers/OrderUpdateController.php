<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Http\Requests\OrderUpdateRequest;
use App\Models\State;

class OrderUpdateController extends Controller
{
    public function __invoke(OrderUpdateRequest $request, Order $order)
    {
    $validated = $request->validated();
    $order->update($validated);
    return response()->json($order);
    }

    public function updateOrderUserReschedule(OrderUpdateRequest $request, Order $order)
    {
        $state = State::where('name', State::RESCHEDULED)->first();

        $date = $request->date;
        $order->date = $date;
        $order->state_id = $state->id;
        $order->update();
        return response()->json($order);
    }

    public function updateOrderReschedule(OrderUpdateRequest $request, Order $order)
    {
        $date = $request->date;
        $order->date = $date;
        $order->update();
        return response()->json($order);
    }

    public function updateOrderRecolectorReschedule(Order $order)
    {
        $state = State::where('name', State::RESCHEDULED)->first();
        $order->state_id = $state->id;
        $order->update();
        return response()->json($order);
    }
}
