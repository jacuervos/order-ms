<?php

namespace Tests\Feature\Order;

use App\Http\Middleware\AuthenticateJwt;
use App\Http\Middleware\RoleMiddleware;
use App\Models\CollectorLocation;
use App\Models\CollectorOrder;
use App\Models\Order;
use App\Models\OrderTypeWaste;
use App\Models\State;
use App\Services\AuthMicroserviceService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Support\CreatesOrderMsSchema;
use Tests\TestCase;

class UserOrderTrackingControllerTest extends TestCase
{
    use CreatesOrderMsSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareOrderMsSchema();
        $this->withoutMiddleware([AuthenticateJwt::class, RoleMiddleware::class]);

        config()->set('services.waste_service.url', 'http://waste.test');

        Http::fake([
            'http://waste.test/type-wastes' => Http::response([
                'data' => [
                    ['id' => 1, 'name' => 'Vidrio'],
                ],
            ], 200),
        ]);
    }

    public function test_user_can_view_assigned_collector_location_when_in_service(): void
    {
        $serviceStartStateId = State::where('name', State::SERVICE_START)->value('id');

        $order = Order::create([
            'user_id' => 25,
            'latitude' => 4.61,
            'longitude' => -74.08,
            'date' => '2026-10-26',
            'state_id' => $serviceStartStateId,
        ]);

        OrderTypeWaste::create([
            'order_id' => $order->id,
            'type_waste_id' => 1,
            'weight' => 4.0,
            'points' => 40,
        ]);

        CollectorOrder::create([
            'order_id' => $order->id,
            'collector_id' => 8,
        ]);

        CollectorLocation::create([
            'collector_id' => 8,
            'latitude' => 4.615,
            'longitude' => -74.082,
        ]);

        $authMock = $this->createMock(AuthMicroserviceService::class);
        $authMock->method('getCollectorById')->willReturn([
            'id' => 8,
            'user_name' => 'Carlos Recolector',
        ]);
        $this->app->instance(AuthMicroserviceService::class, $authMock);

        $token = $this->buildFakeJwt(25, 'Usuario');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/collector/location');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.order_id', $order->id);
        $response->assertJsonPath('data.collector.id', 8);
        $response->assertJsonPath('data.collector.name', 'Carlos Recolector');

        $activeResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/orders/active');

        $activeResponse->assertOk();
        $activeResponse->assertJsonPath('success', true);
        $activeResponse->assertJsonPath('data.0.id', $order->id);
        $activeResponse->assertJsonPath('data.0.status', State::SERVICE_START);
        $activeResponse->assertJsonPath('data.0.collector.name', 'Carlos Recolector');
    }
}

