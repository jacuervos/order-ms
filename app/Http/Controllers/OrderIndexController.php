<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
use App\Models\Order;
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
        $date = $request->date
            ? Carbon::parse($request->date)
            : Carbon::today();
        $orders = Order::whereDate('date', $date)->get();
        $token = $request->bearerToken();
        foreach ($orders as $order) {
            $response = Http::baseUrl(config('services.user_service.url'))
                ->withToken($token)
                ->get("/user/{$order->user_id}");
            $user = $response->json('data');
            $order->user = $user;
        }
        return OrderResource::collection($orders);
    }
}
