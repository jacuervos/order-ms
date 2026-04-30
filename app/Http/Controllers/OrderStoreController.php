<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Http\Requests\OrderStoreRequest;

class OrderStoreController extends Controller
{
    public function __invoke(OrderStoreRequest $request)
    {
    $validated = $request->validated();
    $validated['user_id'] = $request->attributes->get('user_id');
    $order = Order::create($validated);
    return response()->json($order, 201);
    }
}
