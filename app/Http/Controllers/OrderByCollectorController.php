<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\CollectorOrder;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderByCollectorController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $orderService = new OrderService();

        $collectorId = $request->attributes->get('user_id');

        $orderIds = CollectorOrder::where('collector_id', $collectorId)
            ->pluck('order_id');

        /** @var \Illuminate\Database\Eloquent\Collection<int, Order> $orders */
        $orders = Order::whereIn('id', $orderIds)
            ->orderBy('id', 'DESC')
            ->get();

        $token = $request->bearerToken();

        foreach ($orders as $order) {
            $orderService->enrichOrder($order, $token);
        }

        return OrderResource::collection($orders);
    }
}
