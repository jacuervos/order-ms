<?php

namespace Tests\Feature\Order;

use App\Http\Middleware\AuthenticateJwt;
use App\Http\Middleware\RoleMiddleware;
use App\Models\CollectorOrder;
use App\Models\Order;
use App\Models\OrderTypeWaste;
use App\Models\State;
use App\Services\AuthMicroserviceService;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Http;
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

        config()->set('services.user_service.url', 'http://user.test');
        Http::fake([
            'http://user.test/user-points/*' => Http::response(['success' => true], 200),
        ]);
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

        OrderTypeWaste::create([
            'order_id' => $activeOrder->id,
            'type_waste_id' => 1,
            'weight' => 3.0,
            'points' => 30,
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
            'user_name' => 'Maria Lopez',
            'user_phone' => '3001234567',
        ]);
        $authMock->method('getUserById')->willReturn([
            'id' => 15,
            'name' => 'Maria Lopez',
            'phone' => '3001234567',
            'firebase_token' => 'fcm-user-15',
        ]);
        $this->app->instance(AuthMicroserviceService::class, $authMock);

        $pushMock = $this->createMock(PushNotificationService::class);
        $pushMock->method('sendToToken')->willReturn(true);
        $this->app->instance(PushNotificationService::class, $pushMock);

        $token = $this->buildFakeJwt(99, 'Recolector');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/pickup-points');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.total_points', 1);
        $response->assertJsonPath('data.pickup_points.0.order_id', $activeOrder->id);
        $response->assertJsonPath('data.pickup_points.0.user_name', 'Maria Lopez');
        $response->assertJsonPath('data.pickup_points.0.state_name', State::SERVICE_START);

        $detailsResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/orders/{$activeOrder->id}/details");
        $detailsResponse->assertOk()
            ->assertJsonPath('data.order.id', $activeOrder->id);

        $pickedResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/orders/{$activeOrder->id}/picked-up");
        $pickedResponse->assertOk();

        // Calling picked-up again when already picked up returns 200
        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/orders/{$activeOrder->id}/picked-up")
            ->assertOk();

        $completeResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/orders/{$activeOrder->id}/complete");
        $completeResponse->assertOk();

        // Invalid state transitions (400)
        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/orders/{$notStartedOrder->id}/picked-up")
            ->assertStatus(400);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/orders/{$notStartedOrder->id}/complete")
            ->assertStatus(400);

        // Order not assigned to this collector (403)
        $otherOrder = Order::create([
            'user_id' => 17,
            'latitude' => 4.64,
            'longitude' => -74.08,
            'date' => '2026-10-26',
            'state_id' => $serviceStartStateId,
        ]);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/orders/{$otherOrder->id}/details")
            ->assertStatus(403);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/orders/{$otherOrder->id}/picked-up")
            ->assertStatus(403);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/orders/{$otherOrder->id}/complete")
            ->assertStatus(403);

        // Missing token (401)
        $this->withoutHeader('Authorization')->getJson('/api/pickup-points')->assertStatus(401);
        $this->withoutHeader('Authorization')->getJson("/api/orders/{$activeOrder->id}/details")->assertStatus(401);
        $this->withoutHeader('Authorization')->putJson("/api/orders/{$activeOrder->id}/picked-up")->assertStatus(401);
        $this->withoutHeader('Authorization')->putJson("/api/orders/{$activeOrder->id}/complete")->assertStatus(401);
    }

    public function test_pickup_points_rejects_non_collector_user(): void
    {
        $serviceStartStateId = State::where('name', State::SERVICE_START)->value('id');
        $order = Order::create([
            'user_id' => 15,
            'latitude' => 4.62,
            'longitude' => -74.07,
            'date' => '2026-10-26',
            'state_id' => $serviceStartStateId,
        ]);

        $nonCollectorMock = $this->createMock(AuthMicroserviceService::class);
        $nonCollectorMock->method('getAuthenticatedCollector')->willReturn(null);
        $this->app->instance(AuthMicroserviceService::class, $nonCollectorMock);

        $token = $this->buildFakeJwt(99, 'Recolector');
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/pickup-points')->assertStatus(403);
        $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/orders/{$order->id}/details")->assertStatus(403);
        $this->withHeader('Authorization', "Bearer {$token}")->putJson("/api/orders/{$order->id}/picked-up")->assertStatus(403);
        $this->withHeader('Authorization', "Bearer {$token}")->putJson("/api/orders/{$order->id}/complete")->assertStatus(403);
    }

    public function test_pickup_points_handles_auth_service_exceptions(): void
    {
        $serviceStartStateId = State::where('name', State::SERVICE_START)->value('id');
        $order = Order::create([
            'user_id' => 15,
            'latitude' => 4.62,
            'longitude' => -74.07,
            'date' => '2026-10-26',
            'state_id' => $serviceStartStateId,
        ]);

        $exceptionMock = $this->createMock(AuthMicroserviceService::class);
        $exceptionMock->method('getAuthenticatedCollector')->willThrowException(new \RuntimeException('Auth failure'));
        $this->app->instance(AuthMicroserviceService::class, $exceptionMock);

        $token = $this->buildFakeJwt(99, 'Recolector');
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/pickup-points')->assertStatus(500);
        $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/orders/{$order->id}/details")->assertStatus(500);
        $this->withHeader('Authorization', "Bearer {$token}")->putJson("/api/orders/{$order->id}/picked-up")->assertStatus(500);
        $this->withHeader('Authorization', "Bearer {$token}")->putJson("/api/orders/{$order->id}/complete")->assertStatus(500);
    }
}
