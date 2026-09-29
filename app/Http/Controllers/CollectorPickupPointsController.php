<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderTypeWaste;
use App\Models\State;
use App\Services\AuthMicroserviceService;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CollectorPickupPointsController extends Controller
{
    private AuthMicroserviceService $authService;
    private PushNotificationService $pushNotificationService;

    public function __construct(AuthMicroserviceService $authService, PushNotificationService $pushNotificationService)
    {
        $this->authService = $authService;
        $this->pushNotificationService = $pushNotificationService;
    }

    /**
     * HU-19: Obtener los puntos de recogida asignados al recolector
     */
    public function getMyAssignedPickupPoints(): JsonResponse
    {
        try {
            $token = request()->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token no proporcionado',
                ], 401);
            }
            $collectorData = $this->authService->getAuthenticatedCollector($token);
            if (!$collectorData) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no es un recolector',
                ], 403);
            }

            $collectorId = $collectorData['collector']['id'];

            // Obtener las órdenes activas asignadas al recolector a través de collector_orders
            // Filtrar solo órdenes con estado SERVICE_START (Inicio de servicio)
            $orders = Order::whereHas('collectorOrder', function ($query) use ($collectorId) {
                $query->where('collector_id', $collectorId);
            })
            ->whereHas('state', function ($query) {
                $query->whereIn('name', [
                    State::SERVICE_START,
                    State::PICKED_UP,
                ]);
            })
            ->with('state')
            ->get();

            // Compilar todos los puntos de recogida desde las órdenes con datos del usuario
            $pickupPoints = $orders->map(function ($order) use ($token) {
                $userData = null;

                try {
                    if ($order->user_id) {
                        $userData = $this->authService->getUserById((int) $order->user_id, $token);
                    }
                } catch (\Exception $e) {
                    $userData = null;
                }

                return [
                    'id' => $order->id,
                    'order_id' => $order->id,
                    'latitude' => (float) $order->latitude,
                    'longitude' => (float) $order->longitude,
                    'address' => $order->address ?? null,
                    'notes' => $order->notes ?? null,
                    'completed_at' => null,
                    'state_name' => $order->state->name ?? null,
                    'is_picked_up' => ($order->state->name ?? null) === State::PICKED_UP,
                    'user_name' => $userData['name'] ?? $userData['user_name'] ?? 'Usuario',
                    'user_phone' => $userData['phone'] ?? $userData['phone_number'] ?? null,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'total_points' => count($pickupPoints),
                    'pickup_points' => $pickupPoints,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los puntos de recogida',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Marcar una orden como recogida
     */
    public function markPickupPointAsPicked(Order $order): JsonResponse
    {
        try {
            $token = request()->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token no proporcionado',
                ], 401);
            }

            $collectorData = $this->authService->getAuthenticatedCollector($token);

            if (!$collectorData) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no es un recolector',
                ], 403);
            }

            $collectorId = $collectorData['collector']['id'];

            $isAssigned = $order->collectorOrder()->where('collector_id', $collectorId)->exists();

            if (!$isAssigned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta orden no está asignada a tu recolección',
                ], 403);
            }

            $currentStateName = optional($order->state)->name;

            if ($currentStateName === State::PICKED_UP) {
                return response()->json([
                    'success' => true,
                    'message' => 'La orden ya está marcada como recogida',
                    'data' => $order,
                ], 200);
            }

            if ($currentStateName !== State::SERVICE_START) {
                return response()->json([
                    'success' => false,
                    'message' => 'La orden debe estar en Inicio de servicio para marcarla como recogida',
                ], 400);
            }

            $pickedUpState = State::where('name', State::PICKED_UP)->first();

            if (!$pickedUpState) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el estado de recogida',
                ], 500);
            }

            $order->state_id = $pickedUpState->id;
            $order->save();

            try {
                $userData = $this->authService->getUserById((int) $order->user_id, $token);
                $userToken = $this->resolvePushToken($userData);

                if ($userToken) {
                    $this->pushNotificationService->sendToToken(
                        $userToken,
                        'Recogida en curso',
                        'Tu orden #' . $order->id . ' ya fue recogida por el recolector',
                        [
                            'type' => 'order_picked_up',
                            'order_id' => (string) $order->id,
                            'collector_id' => (string) $collectorId,
                            'state' => (string) $pickedUpState->id,
                        ]
                    );
                }
            } catch (\Throwable $pushException) {
                Log::warning('La orden fue marcada como recogida, pero falló la notificación push al usuario', [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'error' => $pushException->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Orden marcada como recogida',
                'data' => $order,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar la orden como recogida',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Marcar una orden como completada
     */
    public function completePickupPoint(Order $order): JsonResponse
    {
        try {
            $token = request()->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token no proporcionado',
                ], 401);
            }

            $collectorData = $this->authService->getAuthenticatedCollector($token);

            if (!$collectorData) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no es un recolector',
                ], 403);
            }

            $collectorId = $collectorData['collector']['id'];

            // Verificar que la orden pertenece al recolector
            $isAssigned = $order->collectorOrder()->where('collector_id', $collectorId)->exists();

            if (!$isAssigned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta orden no está asignada a tu recolección',
                ], 403);
            }

            $currentStateName = optional($order->state)->name;

            if ($currentStateName !== State::PICKED_UP) {
                return response()->json([
                    'success' => false,
                    'message' => 'Primero debes marcar la orden como recogida',
                ], 400);
            }

            $completedState = State::where('name', State::ORDER_COMPLETED)->first();

            if (!$completedState) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el estado de orden finalizada',
                ], 500);
            }

            DB::beginTransaction();

            $totalPoints = OrderTypeWaste::where('order_id', $order->id)
                ->sum('points');

            $order->state_id = $completedState->id;
            $order->save();

            Http::baseUrl(config('services.user_service.url'))
                ->withToken($token)
                ->patch("/user-points/{$order->user_id}", [
                    'points' => $totalPoints,
                ])
                ->throw();

            DB::commit();

            try {
                $userData = $this->authService->getUserById((int) $order->user_id, $token);
                $userToken = $this->resolvePushToken($userData);

                if ($userToken) {
                    $this->pushNotificationService->sendToToken(
                        $userToken,
                        'Orden completada',
                        'Tu orden #' . $order->id . ' fue completada correctamente',
                        [
                            'type' => 'order_completed',
                            'order_id' => (string) $order->id,
                            'collector_id' => (string) $collectorId,
                            'state' => (string) $completedState->id,
                        ]
                    );
                }
            } catch (\Throwable $pushException) {
                Log::warning('La orden fue completada, pero falló la notificación push al usuario', [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'error' => $pushException->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Orden marcada como completada',
                'data' => $order,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al marcar la orden como completada',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener detalles de una orden específica con todos sus puntos de recogida
     */
    public function getOrderDetails(Order $order): JsonResponse
    {
        try {
            $token = request()->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token no proporcionado',
                ], 401);
            }

            $collectorData = $this->authService->getAuthenticatedCollector($token);

            if (!$collectorData) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no es un recolector',
                ], 403);
            }

            $collectorId = $collectorData['collector']['id'];

            // Verificar que la orden está asignada al recolector
            $isAssigned = $order->collectorOrder()->where('collector_id', $collectorId)->exists();

            if (!$isAssigned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta orden no está asignada a ti',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'order' => [
                        'id' => $order->id,
                        'user_name' => $collectorData['user_name'] ?? 'Usuario',
                        'user_phone' => $collectorData['user_phone'] ?? null,
                        'scheduled_date' => $order->date,
                        'status' => $order->state->name ?? 'pending',
                        'latitude' => (float) $order->latitude,
                        'longitude' => (float) $order->longitude,
                        'address' => $order->address ?? 'Sin dirección',
                        'notes' => $order->notes ?? null,
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los detalles de la orden',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function resolvePushToken(?array $userData = null): ?string
    {
        if ($userData === null) {
            return null;
        }

        foreach (['firebase_token', 'user.firebase_token'] as $path) {
            $token = data_get($userData, $path);

            if (is_string($token) && trim($token) !== '') {
                return $token;
            }
        }

        return null;
    }
}
