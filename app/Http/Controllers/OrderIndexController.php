<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Http;

class OrderIndexController extends Controller
{
    public function __invoke()
    {
        return response()->json(Order::all());
    }

    public function indexDashboard(Request $request):  AnonymousResourceCollection
    {
        $orderService = new OrderService();

        $date = $request->date
            ? Carbon::parse($request->date)
            : Carbon::today();
        $orders = Order::whereDate('date', $date)->get();
        $token = $request->bearerToken() ?? '';
        foreach ($orders as $order) {
            $orderService->enrichOrder($order, $token);
        }
        return OrderResource::collection($orders);
    }

    public function index(Request $request)
    {
        $orderService = new OrderService();

        $orders = Order::orderBy('id', 'DESC')
            ->paginate(10);
        $token = $request->bearerToken() ?? '';
        foreach ($orders as $order) {
            $orderService->enrichOrder($order, $token);
        }
        return OrderResource::collection($orders);
    }
}
