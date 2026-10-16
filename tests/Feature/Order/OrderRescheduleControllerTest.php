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

class OrderRescheduleControllerTest extends TestCase
{
    use CreatesOrderMsSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareOrderMsSchema();
        $this->withoutMiddleware([AuthenticateJwt::class, RoleMiddleware::class]);
    }

    public function test_user_can_reschedule_order_and_unassign_collector(): void
    {
        $assignedStateId = State::where('name', State::ASSIGNED_TO_COLLECTOR)->value('id');
        $rescheduledStateId = State::where('name', State::RESCHEDULED)->value('id');

        $order = Order::create([
            'user_id' => 20,
            'latitude' => 4.64,
            'longitude' => -74.06,
            'date' => '2026-10-20',
            'state_id' => $assignedStateId,
        ]);

        CollectorOrder::create([
            'order_id' => $order->id,
            'collector_id' => 9,
        ]);

        $authMock = $this->createMock(AuthMicroserviceService::class);
        $authMock->method('getCollectorById')->willReturn(['id' => 9, 'user_id' => 50]);
        $authMock->method('getUserById')->willReturn(['id' => 50]);
        $this->app->instance(AuthMicroserviceService::class, $authMock);

        $response = $this->putJson("/api/orders/update-date-state/{$order->id}", [
            'date' => '2026-10-30',
        ]);

        $response->assertOk();
        $response->assertJsonPath('date', '2026-10-30');
        $response->assertJsonPath('state_id', $rescheduledStateId);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'date' => '2026-10-30',
            'state_id' => $rescheduledStateId,
        ]);

        $this->assertDatabaseMissing('collector_orders', [
            'order_id' => $order->id,
        ]);
    }
}

