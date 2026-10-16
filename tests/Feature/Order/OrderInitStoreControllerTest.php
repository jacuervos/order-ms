<?php

namespace Tests\Feature\Order;

use App\Http\Middleware\AuthenticateJwt;
use App\Http\Middleware\RoleMiddleware;
use App\Models\CollectorOrder;
use App\Models\Order;
use App\Models\State;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Support\CreatesOrderMsSchema;
use Tests\TestCase;

class OrderInitStoreControllerTest extends TestCase
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
            'http://waste.test/type-wastes' => Http::response(['data' => [['id' => 1, 'name' => 'Cartón']]], 200),
            'http://user.test/user/*' => Http::response(['data' => ['id' => 10, 'name' => 'Ana Usuario']], 200),
            'http://collector.test/collector/*' => Http::response(['data' => ['id' => 5, 'name' => 'Carlos Recolector']], 200),
        ]);
    }

    public function test_admin_can_start_service_for_assigned_orders(): void
    {
        $assignedStateId = State::where('name', State::ASSIGNED_TO_COLLECTOR)->value('id');
        $serviceStartStateId = State::where('name', State::SERVICE_START)->value('id');

        $order1 = Order::create([
            'user_id' => 10,
            'latitude' => 4.65,
            'longitude' => -74.05,
            'date' => '2026-10-26',
            'state_id' => $assignedStateId,
        ]);

        $order2 = Order::create([
            'user_id' => 11,
            'latitude' => 4.66,
            'longitude' => -74.06,
            'date' => '2026-10-26',
            'state_id' => $assignedStateId,
        ]);

        CollectorOrder::create(['order_id' => $order1->id, 'collector_id' => 5]);
        CollectorOrder::create(['order_id' => $order2->id, 'collector_id' => 5]);

        $response = $this->postJson('/api/orders/init', [
            'order_ids' => [$order1->id, $order2->id],
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Órdenes iniciadas correctamente');

        $this->assertDatabaseHas('orders', [
            'id' => $order1->id,
            'state_id' => $serviceStartStateId,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order2->id,
            'state_id' => $serviceStartStateId,
        ]);
    }
}

