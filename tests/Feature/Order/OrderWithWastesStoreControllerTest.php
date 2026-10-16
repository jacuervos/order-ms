<?php

namespace Tests\Feature\Order;

use App\Http\Middleware\AuthenticateJwt;
use App\Http\Middleware\RoleMiddleware;
use App\Models\State;
use Tests\Feature\Support\CreatesOrderMsSchema;
use Tests\TestCase;

class OrderWithWastesStoreControllerTest extends TestCase
{
    use CreatesOrderMsSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareOrderMsSchema();
        $this->withoutMiddleware([AuthenticateJwt::class, RoleMiddleware::class]);
    }

    public function test_user_can_create_order_with_wastes_for_desired_date(): void
    {
        $response = $this->postJson('/api/orders/with-wastes', [
            'latitude' => 4.60971,
            'longitude' => -74.08175,
            'date' => '2026-10-25',
            'items' => [
                [
                    'type_waste_id' => 1,
                    'weight' => 3.5,
                    'points' => 35,
                ],
                [
                    'type_waste_id' => 2,
                    'weight' => 2.0,
                    'points' => 20,
                ],
            ],
        ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Orden creada correctamente');

        $creationStateId = State::where('name', State::ORDER_CREATION)->value('id');

        $this->assertDatabaseHas('orders', [
            'date' => '2026-10-25',
            'state_id' => $creationStateId,
        ]);

        $this->assertDatabaseHas('order_type_wastes', [
            'type_waste_id' => 1,
            'points' => 35,
        ]);

        $this->assertDatabaseHas('order_type_wastes', [
            'type_waste_id' => 2,
            'points' => 20,
        ]);
    }

    public function test_create_order_validates_required_items_and_date(): void
    {
        $response = $this->postJson('/api/orders/with-wastes', [
            'latitude' => 4.60971,
            'longitude' => -74.08175,
            'items' => [],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
    }
}

