<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderByUserController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $orderService = new OrderService();

        $userId = $request->attributes->get('user_id');

        /** @var \Illuminate\Database\Eloquent\Collection<int, Order> $orders */
        $orders = Order::where('user_id', $userId)
            ->orderBy('id', 'DESC')
            ->get();

        $token = $request->bearerToken();

        foreach ($orders as $order) {
            $orderService->enrichOrder($order, $token);
        }

        return OrderResource::collection($orders);
    }
}
