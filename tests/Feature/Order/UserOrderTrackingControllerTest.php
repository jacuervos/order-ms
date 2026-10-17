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
        $rescheduledStateId = State::where('name', State::RESCHEDULED)->value('id');

        $order = Order::create([
            'user_id' => 25,
            'latitude' => 4.61,
            'longitude' => -74.08,
            'date' => '2026-10-26',
            'state_id' => $serviceStartStateId,
        ]);

        Order::create([
            'user_id' => 25,
            'latitude' => 4.62,
            'longitude' => -74.09,
            'date' => '2026-10-28',
            'state_id' => $rescheduledStateId,
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

        $rescheduleResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/orders/reschedule');
        $rescheduleResponse->assertOk();
        $rescheduleResponse->assertJsonPath('success', true);

        // Edge cases: missing token, invalid token, and user without orders
        $this->withoutHeader('Authorization')->getJson('/api/collector/location')->assertStatus(401);
        $this->withoutHeader('Authorization')->getJson('/api/orders/active')->assertStatus(401);
        $this->withoutHeader('Authorization')->getJson('/api/orders/reschedule')->assertStatus(401);

        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/collector/location')
            ->assertStatus(401);

        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/orders/active')
            ->assertStatus(401);

        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/orders/reschedule')
            ->assertStatus(401);

        $emptyUserToken = $this->buildFakeJwt(999, 'Usuario');
        $this->withHeader('Authorization', "Bearer {$emptyUserToken}")
            ->getJson('/api/collector/location')
            ->assertStatus(404);

        // User with order that has no assigned collector yet (404)
        $unassignedUserToken = $this->buildFakeJwt(777, 'Usuario');
        Order::create([
            'user_id' => 777,
            'latitude' => 4.61,
            'longitude' => -74.08,
            'date' => '2026-10-26',
            'state_id' => $serviceStartStateId,
        ]);
        $this->withHeader('Authorization', "Bearer {$unassignedUserToken}")
            ->getJson('/api/collector/location')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Aún no se ha asignado un recolector');
    }

    public function test_tracking_handles_auth_service_and_database_exceptions(): void
    {
        $serviceStartStateId = State::where('name', State::SERVICE_START)->value('id');
        $order = Order::create([
            'user_id' => 25,
            'latitude' => 4.61,
            'longitude' => -74.08,
            'date' => '2026-10-26',
            'state_id' => $serviceStartStateId,
        ]);
        CollectorOrder::create(['order_id' => $order->id, 'collector_id' => 8]);

        $failingAuthMock = $this->createMock(AuthMicroserviceService::class);
        $failingAuthMock->method('getCollectorById')->willThrowException(new \RuntimeException('Auth MS down'));
        $this->app->instance(AuthMicroserviceService::class, $failingAuthMock);

        $token = $this->buildFakeJwt(25, 'Usuario');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/collector/location')
            ->assertStatus(500);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/orders/active')
            ->assertOk()
            ->assertJsonPath('data.0.collector.name', 'Recolector');

        \Illuminate\Support\Facades\Schema::drop('orders');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/orders/active')
            ->assertStatus(500);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/orders/reschedule')
            ->assertStatus(500);
    }
}
