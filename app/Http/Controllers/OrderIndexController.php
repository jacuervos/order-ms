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
        $typeWasteResponse = Http::baseUrl(config('services.waste_service.url'))
            ->withToken($token)
            ->get("/type-wastes");
        $typeWastes = collect($typeWasteResponse->json('data'))
            ->keyBy('id');
        foreach ($orders as $order) {
            $response = Http::baseUrl(config('services.user_service.url'))
                ->withToken($token)
                ->get("/user/{$order->user_id}");
            $user = $response->json('data');
            $order->orderTypeWastes->transform(function ($waste) use ($typeWastes) {
                $waste->type_waste = $typeWastes[$waste->type_waste_id]['name'] ?? null;
                return $waste;
            });
            $order->user = $user;
        }
        return OrderResource::collection($orders);
    }
}
