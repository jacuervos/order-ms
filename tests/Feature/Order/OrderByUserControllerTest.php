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

class OrderByUserControllerTest extends TestCase
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
                'data' => [
                    ['id' => 1, 'name' => 'Plástico'],
                    ['id' => 2, 'name' => 'Cartón'],
                ],
            ], 200),
            'http://user.test/user/*' => Http::response([
                'data' => ['id' => 42, 'name' => 'Maria Lopez'],
            ], 200),
        ]);
    }

    public function test_user_can_view_order_history_sorted_descending(): void
    {
        $completedStateId = State::where('name', State::ORDER_COMPLETED)->value('id');
        $creationStateId = State::where('name', State::ORDER_CREATION)->value('id');

        $firstOrder = Order::create([
            'user_id' => 42,
            'latitude' => 4.61,
            'longitude' => -74.08,
            'date' => '2026-10-10',
            'state_id' => $completedStateId,
        ]);

        $secondOrder = Order::create([
            'user_id' => 42,
            'latitude' => 4.62,
            'longitude' => -74.07,
            'date' => '2026-10-18',
            'state_id' => $creationStateId,
        ]);

        // Order belonging to another user should not be returned
        Order::create([
            'user_id' => 99,
            'latitude' => 4.70,
            'longitude' => -74.05,
            'date' => '2026-10-19',
            'state_id' => $creationStateId,
        ]);

        OrderTypeWaste::create([
            'order_id' => $firstOrder->id,
            'type_waste_id' => 1,
            'weight' => 3.0,
            'points' => 30,
        ]);

        $token = $this->buildFakeJwt(42, 'Usuario');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/orders/my-orders');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', $secondOrder->id);
        $response->assertJsonPath('data.1.id', $firstOrder->id);
        $response->assertJsonPath('data.1.state.name', State::ORDER_COMPLETED);
    }
}

