<?php

namespace App\Http\Controllers;

use App\Models\CollectorOrder;
use App\Models\Order;
use App\Http\Requests\OrderUpdateRequest;
use App\Models\State;
use App\Services\AuthMicroserviceService;
use App\Services\PushNotificationService;

class OrderUpdateController extends Controller
{

    private AuthMicroserviceService $authService;
    private PushNotificationService $pushNotificationService;

    public function __construct(AuthMicroserviceService $authService, PushNotificationService $pushNotificationService)
    {
        $this->authService = $authService;
        $this->pushNotificationService = $pushNotificationService;
    }

    public function __invoke(OrderUpdateRequest $request, Order $order)
    {
    $validated = $request->validated();
    $order->update($validated);
    return response()->json($order);
    }

    public function updateOrderUserReschedule(OrderUpdateRequest $request, Order $order)
    {
        $token = request()->bearerToken() ?? '';
        $state = State::where('name', State::RESCHEDULED)->first();
        $collectorOrder = CollectorOrder::where('order_id', $order->id)->first();

        if ($collectorOrder) {
            if ($token !== '') {
                try {
                    $collectorData = $this->authService->getCollectorById((int) $collectorOrder->collector_id, $token);
                    $userId = data_get($collectorData, 'user_id') ?? data_get($collectorData, 'user.id') ?? data_get($collectorData, 'id_user');

                    $userData = null;
                    if ($userId !== null && $userId !== '') {
                        $userData = $this->authService->getUserById((int) $userId, $token);
                    }
                    $collectorToken = $this->resolvePushToken($collectorData, $userData);
                    if ($collectorToken) {
                        $this->pushNotificationService->sendToToken(
                            $collectorToken,
                            'Órden reprogramada',
                            'La orden #' . $order->id . ' se reprogramo, ya  no debe dirigirse al sitio',
                            [
                                'type' => 'reschedule_order',
                                'order_id' => (string) $order->id,
                            ]
                        );
                    }
                } catch (\Throwable $e) {
                    // Continue rescheduling even if push notification fails
                }
            }
            $collectorOrder->delete();
        }
        $date = $request->date;
        $order->date = $date;
        $order->state_id = $state->id;
        $order->update();
        return response()->json($order);
    }

    public function updateOrderReschedule(OrderUpdateRequest $request, Order $order)
    {
        $token = request()->bearerToken() ?? '';
        $collectorOrder = CollectorOrder::where('order_id', $order->id)->first();

        if ($collectorOrder) {
            if ($token !== '') {
                try {
                    $collectorData = $this->authService->getCollectorById((int) $collectorOrder->collector_id, $token);
                    $userId = data_get($collectorData, 'user_id') ?? data_get($collectorData, 'user.id') ?? data_get($collectorData, 'id_user');

                    $userData = null;
                    if ($userId !== null && $userId !== '') {
                        $userData = $this->authService->getUserById((int) $userId, $token);
                    }
                    $collectorToken = $this->resolvePushToken($collectorData, $userData);
                    if ($collectorToken) {
                        $this->pushNotificationService->sendToToken(
                            $collectorToken,
                            'Órden reprogramada',
                            'La orden #' . $order->id . ' se reprogramo, ya  no debe dirigirse al sitio',
                            [
                                'type' => 'reschedule_order',
                                'order_id' => (string) $order->id,
                            ]
                        );
                    }
                } catch (\Throwable $e) {
                    // Continue rescheduling even if push notification fails
                }
            }
            $collectorOrder->delete();
        }
        $date = $request->date;
        $order->date = $date;
        $order->update();
        return response()->json($order);
    }

    public function updateOrderRecolectorReschedule(Order $order)
    {
        $token = request()->bearerToken() ?? '';

        $state = State::where('name', State::RESCHEDULED)->first();
        $collectorOrder = CollectorOrder::where('order_id', $order->id)->first();

        if ($collectorOrder) {
            if ($token !== '') {
                try {
                    $userData = $this->authService->getUserById((int) $order->user_id, $token);
                    $userToken = $this->resolvePushToken($userData);
                    if ($userToken) {
                        $this->pushNotificationService->sendToToken(
                            $userToken,
                            'Recogida en curso',
                            'Tu orden #' . $order->id . ' ha sido reprogramada',
                            [
                                'type' => 'order_picked_up',
                                'order_id' => (string) $order->id,
                            ]
                        );
                    }
                } catch (\Throwable $e) {
                    // Continue rescheduling even if push notification fails
                }
            }
            $collectorOrder->delete();
        }
        $order->state_id = $state->id;
        $order->update();
        return response()->json($order);
    }

    private function resolvePushToken(mixed $collectorData, ?array $userData = null): ?string
    {
        foreach (['firebase_token', 'user.firebase_token', 'collector.firebase_token'] as $path) {
            $token = data_get($collectorData, $path);

            if (is_string($token) && trim($token) !== '') {
                return $token;
            }
        }

        if ($userData !== null) {
            $token = data_get($userData, 'firebase_token');

            if (is_string($token) && trim($token) !== '') {
                return $token;
            }
        }

        return null;
    }
}
