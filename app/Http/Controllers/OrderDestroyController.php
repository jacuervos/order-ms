<?php

namespace App\Http\Controllers;

use App\Models\Order;

class OrderDestroyController extends Controller
{
    public function __invoke(Order $order)
    {
        $order->delete();
        return response()->json(null, 204);
    }
}
