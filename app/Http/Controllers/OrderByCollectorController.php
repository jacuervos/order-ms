<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\CollectorOrder;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\AuthMicroserviceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderByCollectorController extends Controller
{
    private AuthMicroserviceService $authService;

    public function __construct(AuthMicroserviceService $authService)
    {
        $this->authService = $authService;
    }

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $orderService = new OrderService();

        $token = $request->bearerToken();

        if (!$token) {
            return OrderResource::collection(collect());
        }

        $collectorData = $this->authService->getAuthenticatedCollector($token);
        $collectorId = $collectorData['collector']['id'] ?? null;

        if (!$collectorId) {
            return OrderResource::collection(collect());
        }

        $orderIds = CollectorOrder::where('collector_id', $collectorId)
            ->pluck('order_id');

        /** @var \Illuminate\Database\Eloquent\Collection<int, Order> $orders */
        $orders = Order::whereIn('id', $orderIds)
            ->orderBy('id', 'DESC')
            ->get();

        foreach ($orders as $order) {
            $orderService->enrichOrder($order, $token);
        }

        return OrderResource::collection($orders);
    }
}
