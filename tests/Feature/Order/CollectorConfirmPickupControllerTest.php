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
    }
}
