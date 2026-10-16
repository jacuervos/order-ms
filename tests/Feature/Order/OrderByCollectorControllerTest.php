<?php

namespace Tests\Feature\Order;

use App\Http\Middleware\AuthenticateJwt;
use App\Http\Middleware\RoleMiddleware;
use App\Models\CollectorOrder;
use App\Models\Order;
use App\Models\OrderTypeWaste;
use App\Models\State;
use App\Services\AuthMicroserviceService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Support\CreatesOrderMsSchema;
use Tests\TestCase;

class OrderByCollectorControllerTest extends TestCase
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
                'data' => [['id' => 1, 'name' => 'Vidrio']],
            ], 200),
            'http://user.test/user/*' => Http::response([
                'data' => ['id' => 15, 'name' => 'Ana Usuario'],
            ], 200),
            'http://collector.test/collector/*' => Http::response([
                'data' => ['id' => 7, 'name' => 'Carlos Recolector'],
            ], 200),
        ]);
    }

    public function test_collector_can_view_assigned_and_completed_orders_history(): void
    {
        $completedStateId = State::where('name', State::ORDER_COMPLETED)->value('id');
        $assignedStateId = State::where('name', State::ASSIGNED_TO_COLLECTOR)->value('id');

        $order1 = Order::create([
            'user_id' => 15,
            'latitude' => 4.63,
            'longitude' => -74.08,
            'date' => '2026-10-12',
            'state_id' => $completedStateId,
        ]);

        $order2 = Order::create([
            'user_id' => 16,
            'latitude' => 4.64,
            'longitude' => -74.09,
            'date' => '2026-10-19',
            'state_id' => $assignedStateId,
        ]);

        // Order assigned to another collector
        $otherOrder = Order::create([
            'user_id' => 17,
            'latitude' => 4.65,
            'longitude' => -74.10,
            'date' => '2026-10-20',
            'state_id' => $assignedStateId,
        ]);

        CollectorOrder::create(['order_id' => $order1->id, 'collector_id' => 7]);
        CollectorOrder::create(['order_id' => $order2->id, 'collector_id' => 7]);
        CollectorOrder::create(['order_id' => $otherOrder->id, 'collector_id' => 99]);

        OrderTypeWaste::create([
            'order_id' => $order1->id,
            'type_waste_id' => 1,
            'weight' => 4.5,
            'points' => 45,
        ]);

        $authMock = $this->createMock(AuthMicroserviceService::class);
        $authMock->method('getAuthenticatedCollector')->willReturn([
            'collector' => ['id' => 7],
        ]);
        $this->app->instance(AuthMicroserviceService::class, $authMock);

        $token = $this->buildFakeJwt(70, 'Recolector');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/orders/my-collections');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', $order2->id);
        $response->assertJsonPath('data.1.id', $order1->id);
        $response->assertJsonPath('data.1.state.name', State::ORDER_COMPLETED);
    }
}

