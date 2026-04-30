<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Http\Requests\OrderUpdateRequest;

class OrderUpdateController extends Controller
{
    public function __invoke(OrderUpdateRequest $request, Order $order)
    {
    $validated = $request->validated();
    $order->update($validated);
    return response()->json($order);
    }
}
