<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;

class OrderService
{
    public function enrichOrder(Order $order, string $token): Order
    {
        $typeWasteResponse = Http::baseUrl(config('services.waste_service.url'))
            ->withToken($token)
            ->get("/type-wastes");

        $typeWastes = collect($typeWasteResponse->json('data'))
            ->keyBy('id');

        $userResponse = Http::baseUrl(config('services.user_service.url'))
            ->withToken($token)
            ->get("/user/{$order->user_id}");

        $order->user = $userResponse->json('data');

        $order->orderTypeWastes->transform(function ($waste) use ($typeWastes) {
            $waste->type_waste = $typeWastes[$waste->type_waste_id]['name'] ?? null;
            return $waste;
        });

        return $order;
    }
}