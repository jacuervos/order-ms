<?php

namespace Tests\Feature\Order;

use App\Http\Middleware\AuthenticateJwt;
use App\Http\Middleware\RoleMiddleware;
use App\Models\Order;
use App\Models\OrderTypeWaste;
use App\Models\State;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Support\CreatesOrderMsSchema;
use Tests\TestCase;

class OrderIndexControllerTest extends TestCase
{
    use CreatesOrderMsSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareOrderMsSchema();
        $this->withoutMiddleware([AuthenticateJwt::class, RoleMiddleware::class]);

        config()->set('services.waste_service.url', 'http://waste.test');
        config()->set('services.user_service.url', 'http://user.test');
        config()->set('services.collector_service.url', 'http://collector.test');

        Http::fake([
            'http://waste.test/type-wastes' => Http::response([
                'data' => [['id' => 1, 'name' => 'Metal']],
            ], 200),
            'http://user.test/user/*' => Http::response([
                'data' => ['id' => 10, 'name' => 'Usuario General'],
            ], 200),
        ]);
    }

    public function test_admin_can_view_all_created_orders_paginated_and_by_date(): void
    {
        $creationStateId = State::where('name', State::ORDER_CREATION)->value('id');
        $completedStateId = State::where('name', State::ORDER_COMPLETED)->value('id');

        $order1 = Order::create([
            'user_id' => 10,
            'latitude' => 4.61,
            'longitude' => -74.07,
            'date' => '2026-10-20',
            'state_id' => $creationStateId,
        ]);

        $order2 = Order::create([
            'user_id' => 11,
            'latitude' => 4.62,
            'longitude' => -74.08,
            'date' => '2026-10-21',
            'state_id' => $completedStateId,
        ]);

        OrderTypeWaste::create([
            'order_id' => $order1->id,
            'type_waste_id' => 1,
            'weight' => 2.5,
            'points' => 25,
        ]);

        $allOrdersResponse = $this->getJson('/api/orders?page=1');

        $allOrdersResponse->assertOk();
        $allOrdersResponse->assertJsonCount(2, 'data');
        $allOrdersResponse->assertJsonPath('data.0.id', $order2->id);
        $allOrdersResponse->assertJsonPath('data.1.id', $order1->id);

        $dashboardResponse = $this->getJson('/api/orders/dashboard?date=2026-10-20');

        $dashboardResponse->assertOk();
        $dashboardResponse->assertJsonCount(1, 'data');
        $dashboardResponse->assertJsonPath('data.0.id', $order1->id);
    }
}

