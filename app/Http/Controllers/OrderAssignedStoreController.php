<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderAssignedStoreRequest;
use App\Http\Resources\OrderResource;
use App\Models\CollectorOrder;
use App\Models\Order;
use App\Models\State;
use App\Services\AuthMicroserviceService;
use App\Services\OrderService;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderAssignedStoreController extends Controller
{
    public function __invoke(
        OrderAssignedStoreRequest $request,
        OrderService $orderService,
        AuthMicroserviceService $authService,
        PushNotificationService $pushNotificationService
    ): JsonResponse {
        DB::beginTransaction();

        try {
            $validated = $request->validated();
            $order = Order::findOrFail($validated['order_id']);

            CollectorOrder::create([
                'order_id' => $validated['order_id'],
                'collector_id' => $validated['collector_id'],
            ]);

            $stateId = State::where('name', State::ASSIGNED_TO_COLLECTOR)->value('id');

            $order->update([
                'state_id' => $stateId,
            ]);

            DB::commit();

            $token = $request->bearerToken();

            if ($token) {
                try {
                    $collectorData = $authService->getCollectorById((int) $validated['collector_id'], $token);
                    $userId = data_get($collectorData, 'user_id') ?? data_get($collectorData, 'user.id') ?? data_get($collectorData, 'id_user');

                    $userData = null;
                    if ($userId !== null && $userId !== '') {
                        $userData = $authService->getUserById((int) $userId, $token);
                    }

                    $collectorToken = $this->resolvePushToken($collectorData, $userData);

                    if ($collectorToken) {
                        $pushNotificationService->sendToToken(
                            $collectorToken,
                            'Nueva orden asignada',
                            'Se te asignó la orden #' . $order->id,
                            [
                                'type' => 'order_assigned',
                                'order_id' => (string) $order->id,
                                'collector_id' => (string) $validated['collector_id'],
                                'state' => (string) $stateId,
                            ]
                        );
                    }
                } catch (\Throwable $pushException) {
                    Log::warning('La orden fue asignada, pero falló la notificación push al recolector', [
                        'collector_id' => $validated['collector_id'],
                        'order_id' => $validated['order_id'],
                        'error' => $pushException->getMessage(),
                    ]);
                }
            }

            $order = $orderService->enrichOrder($order, $token ?? '');

            return response()->json([
                'success' => true,
                'message' => 'Recolector asignado correctamente',
                'data' => new OrderResource($order),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al asignar el recolector: ' . $e->getMessage(),
            ], 500);
        }
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
