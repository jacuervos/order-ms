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
            'http://user.test/user/*' => Http::response(['data' => ['id' => 10, 'name' => 'Ana Usuario', 'firebase_token' => 'user-fcm-token']], 200),
            'http://collector.test/collector/*' => Http::response(['data' => ['id' => 5, 'user_id' => 10, 'name' => 'Carlos Recolector']], 200),
            '*' => Http::response(['name' => 'projects/test/messages/1'], 200),
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

        $response = $this->withHeader('Authorization', 'Bearer test-token')
            ->postJson('/api/orders/assign-collector', [
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

        // Test when collector directly has firebase_token and push service throws exception
        $pushMock = $this->createMock(\App\Services\PushNotificationService::class);
        $pushMock->method('sendToToken')->willThrowException(new \RuntimeException('FCM error'));
        $this->app->instance(\App\Services\PushNotificationService::class, $pushMock);

        $authMock = $this->createMock(\App\Services\AuthMicroserviceService::class);
        $authMock->method('getCollectorById')->willReturn([
            'id' => 5,
            'firebase_token' => 'collector-direct-fcm-token',
        ]);
        $this->app->instance(\App\Services\AuthMicroserviceService::class, $authMock);

        $order2 = Order::create([
            'user_id' => 10,
            'latitude' => 4.65,
            'longitude' => -74.05,
            'date' => '2026-10-26',
            'state_id' => $creationStateId,
        ]);

        $this->withHeader('Authorization', 'Bearer test-token')
            ->postJson('/api/orders/assign-collector', [
                'order_id' => $order2->id,
                'collector_id' => 5,
            ])
            ->assertCreated();

        // Test when neither collector nor user has a firebase_token
        $authMockEmpty = $this->createMock(\App\Services\AuthMicroserviceService::class);
        $authMockEmpty->method('getCollectorById')->willReturn(['id' => 5, 'user_id' => 10]);
        $authMockEmpty->method('getUserById')->willReturn(['id' => 10]);
        $this->app->instance(\App\Services\AuthMicroserviceService::class, $authMockEmpty);

        $order3 = Order::create([
            'user_id' => 10,
            'latitude' => 4.65,
            'longitude' => -74.05,
            'date' => '2026-10-26',
            'state_id' => $creationStateId,
        ]);

        $this->withHeader('Authorization', 'Bearer test-token')
            ->postJson('/api/orders/assign-collector', [
                'order_id' => $order3->id,
                'collector_id' => 5,
            ])
            ->assertCreated();

        // Test database exception rollback (500)
        \Illuminate\Support\Facades\Schema::drop('collector_orders');

        $this->withHeader('Authorization', 'Bearer test-token')
            ->postJson('/api/orders/assign-collector', [
                'order_id' => $order->id,
                'collector_id' => 5,
            ])
            ->assertStatus(500)
            ->assertJsonPath('success', false);
    }
}

