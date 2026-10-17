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

class CollectorConfirmPickupControllerTest extends TestCase
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

    public function test_collector_can_mark_order_as_picked_up_and_completed(): void
    {
        $serviceStartStateId = State::where('name', State::SERVICE_START)->value('id');
        $pickedUpStateId = State::where('name', State::PICKED_UP)->value('id');
        $completedStateId = State::where('name', State::ORDER_COMPLETED)->value('id');

        $order = Order::create([
            'user_id' => 30,
            'latitude' => 4.65,
            'longitude' => -74.08,
            'date' => '2026-10-26',
            'state_id' => $serviceStartStateId,
        ]);

        OrderTypeWaste::create([
            'order_id' => $order->id,
            'type_waste_id' => 1,
            'weight' => 5.0,
            'points' => 50,
        ]);

        CollectorOrder::create([
            'order_id' => $order->id,
            'collector_id' => 14,
        ]);

        $authMock = $this->createMock(AuthMicroserviceService::class);
        $authMock->method('getAuthenticatedCollector')->willReturn([
            'collector' => ['id' => 14],
            'user_name' => 'Pedro Usuario',
        ]);
        $authMock->method('getUserById')->willReturn([
            'id' => 30,
            'name' => 'Pedro Usuario',
            'firebase_token' => 'fcm-token-30',
        ]);
        $this->app->instance(AuthMicroserviceService::class, $authMock);

        $pushMock = $this->createMock(PushNotificationService::class);
        $pushMock->method('sendToToken')->willReturn(true);
        $this->app->instance(PushNotificationService::class, $pushMock);

        $token = $this->buildFakeJwt(88, 'Recolector');

        $pointsResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/pickup-points');
        $pointsResponse->assertOk();

        $detailsResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/orders/{$order->id}/details");
        $detailsResponse->assertOk();

        $pickedResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/orders/{$order->id}/picked-up");

        $pickedResponse->assertOk();
        $pickedResponse->assertJsonPath('success', true);
        $pickedResponse->assertJsonPath('message', 'Orden marcada como recogida');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'state_id' => $pickedUpStateId,
        ]);

        // Calling picked-up again when already picked up returns 200
        $alreadyPickedResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/orders/{$order->id}/picked-up");
        $alreadyPickedResponse->assertOk();

        $completeResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/orders/{$order->id}/complete");

        $completeResponse->assertOk();
        $completeResponse->assertJsonPath('success', true);
        $completeResponse->assertJsonPath('message', 'Orden marcada como completada');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'state_id' => $completedStateId,
        ]);

        // Invalid state transitions (400)
        $assignedStateId = State::where('name', State::ASSIGNED_TO_COLLECTOR)->value('id');
        $notStartedOrder = Order::create([
            'user_id' => 31,
            'latitude' => 4.66,
            'longitude' => -74.09,
            'date' => '2026-10-26',
            'state_id' => $assignedStateId,
        ]);
        CollectorOrder::create(['order_id' => $notStartedOrder->id, 'collector_id' => 14]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/orders/{$notStartedOrder->id}/picked-up")
            ->assertStatus(400);
        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/orders/{$notStartedOrder->id}/complete")
            ->assertStatus(400);

        // Order not assigned to this collector (403)
        $otherOrder = Order::create([
            'user_id' => 32,
            'latitude' => 4.67,
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
        $this->withoutHeader('Authorization')->getJson("/api/orders/{$order->id}/details")->assertStatus(401);
        $this->withoutHeader('Authorization')->putJson("/api/orders/{$order->id}/picked-up")->assertStatus(401);
        $this->withoutHeader('Authorization')->putJson("/api/orders/{$order->id}/complete")->assertStatus(401);
    }

    public function test_confirm_pickup_rejects_non_collector_user(): void
    {
        $serviceStartStateId = State::where('name', State::SERVICE_START)->value('id');
        $order = Order::create([
            'user_id' => 30,
            'latitude' => 4.65,
            'longitude' => -74.08,
            'date' => '2026-10-26',
            'state_id' => $serviceStartStateId,
        ]);

        $nonCollectorMock = $this->createMock(AuthMicroserviceService::class);
        $nonCollectorMock->method('getAuthenticatedCollector')->willReturn(null);
        $this->app->instance(AuthMicroserviceService::class, $nonCollectorMock);

        $token = $this->buildFakeJwt(88, 'Recolector');
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/pickup-points')->assertStatus(403);
        $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/orders/{$order->id}/details")->assertStatus(403);
        $this->withHeader('Authorization', "Bearer {$token}")->putJson("/api/orders/{$order->id}/picked-up")->assertStatus(403);
        $this->withHeader('Authorization', "Bearer {$token}")->putJson("/api/orders/{$order->id}/complete")->assertStatus(403);
    }

    public function test_confirm_pickup_handles_auth_service_exceptions(): void
    {
        $serviceStartStateId = State::where('name', State::SERVICE_START)->value('id');
        $order = Order::create([
            'user_id' => 30,
            'latitude' => 4.65,
            'longitude' => -74.08,
            'date' => '2026-10-26',
            'state_id' => $serviceStartStateId,
        ]);

        $exceptionMock = $this->createMock(AuthMicroserviceService::class);
        $exceptionMock->method('getAuthenticatedCollector')->willThrowException(new \RuntimeException('Auth failure'));
        $this->app->instance(AuthMicroserviceService::class, $exceptionMock);

        $token = $this->buildFakeJwt(88, 'Recolector');
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/pickup-points')->assertStatus(500);
        $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/orders/{$order->id}/details")->assertStatus(500);
        $this->withHeader('Authorization', "Bearer {$token}")->putJson("/api/orders/{$order->id}/picked-up")->assertStatus(500);
        $this->withHeader('Authorization', "Bearer {$token}")->putJson("/api/orders/{$order->id}/complete")->assertStatus(500);
    }
}
