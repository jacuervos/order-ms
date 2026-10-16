<?php

namespace Tests\Feature\Order;

use App\Http\Middleware\AuthenticateJwt;
use App\Http\Middleware\RoleMiddleware;
use App\Models\Order;
use App\Models\State;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Support\CreatesOrderMsSchema;
use Tests\TestCase;

class OrderAssignedStoreControllerTest extends TestCase
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
            'http://waste.test/type-wastes' => Http::response(['data' => [['id' => 1, 'name' => 'Plástico']]], 200),
            'http://user.test/user/*' => Http::response(['data' => ['id' => 10, 'name' => 'Ana Usuario']], 200),
            'http://collector.test/collector/*' => Http::response(['data' => ['id' => 5, 'name' => 'Carlos Recolector']], 200),
        ]);
    }

    public function test_admin_can_assign_collector_to_order(): void
    {
        $creationStateId = State::where('name', State::ORDER_CREATION)->value('id');
        $assignedStateId = State::where('name', State::ASSIGNED_TO_COLLECTOR)->value('id');

        $order = Order::create([
            'user_id' => 10,
            'latitude' => 4.65,
            'longitude' => -74.05,
            'date' => '2026-10-26',
            'state_id' => $creationStateId,
        ]);

        $response = $this->postJson('/api/orders/assign-collector', [
            'order_id' => $order->id,
            'collector_id' => 5,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Recolector asignado correctamente');
        $response->assertJsonPath('data.state.name', State::ASSIGNED_TO_COLLECTOR);

        $this->assertDatabaseHas('collector_orders', [
            'order_id' => $order->id,
            'collector_id' => 5,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'state_id' => $assignedStateId,
        ]);
    }
}

