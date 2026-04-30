<?php

namespace App\Http\Controllers;

use App\Models\Order;

class OrderShowController extends Controller
{
    public function __invoke(Order $order)
    {
        return response()->json($order);
    }
}
