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

class OrderCancelControllerTest extends TestCase
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
            'http://waste.test/type-wastes' => Http::response(['data' => [['id' => 1, 'name' => 'Papel']]], 200),
            'http://user.test/user/*' => Http::response(['data' => ['id' => 10, 'name' => 'Ana Usuario']], 200),
        ]);
    }

    public function test_admin_can_cancel_order_and_reset_points_to_zero(): void
    {
        $creationStateId = State::where('name', State::ORDER_CREATION)->value('id');
        $cancelStateId = State::where('name', State::CANCEL)->value('id');

        $order = Order::create([
            'user_id' => 10,
            'latitude' => 4.65,
            'longitude' => -74.05,
            'date' => '2026-10-26',
            'state_id' => $creationStateId,
        ]);

        OrderTypeWaste::create([
            'order_id' => $order->id,
            'type_waste_id' => 1,
            'weight' => 4.0,
            'points' => 40,
        ]);

        $response = $this->postJson('/api/orders/cancel', [
            'order_id' => $order->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Órdenes cancelada correctamente');
        $response->assertJsonPath('data.state.name', State::CANCEL);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'state_id' => $cancelStateId,
        ]);

        $this->assertDatabaseHas('order_type_wastes', [
            'order_id' => $order->id,
            'points' => 0,
        ]);
    }
}

