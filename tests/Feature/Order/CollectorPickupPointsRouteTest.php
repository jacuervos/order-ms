<?php

namespace Tests\Feature\Order;

use App\Http\Middleware\AuthenticateJwt;
use App\Http\Middleware\RoleMiddleware;
use App\Models\CollectorOrder;
use App\Models\Order;
use App\Models\State;
use App\Services\AuthMicroserviceService;
use Tests\Feature\Support\CreatesOrderMsSchema;
use Tests\TestCase;

class CollectorPickupPointsRouteTest extends TestCase
{
    use CreatesOrderMsSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareOrderMsSchema();
        $this->withoutMiddleware([AuthenticateJwt::class, RoleMiddleware::class]);
    }

    public function test_collector_can_view_assigned_route_pickup_points(): void
    {
        $serviceStartStateId = State::where('name', State::SERVICE_START)->value('id');
        $assignedStateId = State::where('name', State::ASSIGNED_TO_COLLECTOR)->value('id');

        $activeOrder = Order::create([
            'user_id' => 15,
            'latitude' => 4.62,
            'longitude' => -74.07,
            'date' => '2026-10-26',
            'address' => 'Calle 40 #13-50',
            'state_id' => $serviceStartStateId,
        ]);

        $notStartedOrder = Order::create([
            'user_id' => 16,
            'latitude' => 4.63,
            'longitude' => -74.09,
            'date' => '2026-10-26',
            'state_id' => $assignedStateId,
        ]);

        CollectorOrder::create(['order_id' => $activeOrder->id, 'collector_id' => 12]);
        CollectorOrder::create(['order_id' => $notStartedOrder->id, 'collector_id' => 12]);

        $authMock = $this->createMock(AuthMicroserviceService::class);
        $authMock->method('getAuthenticatedCollector')->willReturn([
            'collector' => ['id' => 12],
        ]);
        $authMock->method('getUserById')->willReturn([
            'id' => 15,
            'name' => 'Maria Lopez',
            'phone' => '3001234567',
        ]);
        $this->app->instance(AuthMicroserviceService::class, $authMock);

        $token = $this->buildFakeJwt(99, 'Recolector');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/pickup-points');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.total_points', 1);
        $response->assertJsonPath('data.pickup_points.0.order_id', $activeOrder->id);
        $response->assertJsonPath('data.pickup_points.0.user_name', 'Maria Lopez');
        $response->assertJsonPath('data.pickup_points.0.state_name', State::SERVICE_START);
    }
}

