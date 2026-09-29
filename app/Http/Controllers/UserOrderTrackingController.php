<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\CollectorLocation;
use App\Models\State;
use App\Services\AuthMicroserviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class UserOrderTrackingController extends Controller
{
    private AuthMicroserviceService $authService;

    public function __construct(AuthMicroserviceService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * HU-18: Obtener el recolector asignado y su ubicación actual
     */
    public function getMyCollectorLocation(): JsonResponse
    {
        try {
            $token = request()->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token no proporcionado',
                ], 401);
            }

            $userId = $this->getUserIdFromToken($token);

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener el usuario',
                ], 401);
            }

            // Obtener la orden activa/más reciente del usuario
            $order = Order::where('user_id', $userId)
                ->where(function ($query) {
                    $query->whereNull('completed_at')
                        ->orWhere('completed_at', '>', now()->subHours(24));
                })
                ->with('collectorOrder')
                ->latest('created_at')
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay recolector asignado en este momento',
                ], 404);
            }

            if (!$order->collectorOrder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aún no se ha asignado un recolector',
                ], 404);
            }

            $collectorId = $order->collectorOrder->collector_id;

            // Obtener la ubicación actual del recolector
            $collectorLocation = CollectorLocation::where('collector_id', $collectorId)
                ->latest('updated_at')
                ->first();

            // Obtener datos del collector de Auth MS
            $collectorData = $this->authService->getCollectorById($collectorId, $token);

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'collector' => [
                        'id' => $collectorId,
                        'name' => $collectorData['user_name'] ?? 'Recolector',
                        'location' => $collectorLocation ? [
                            'latitude' => $collectorLocation->latitude,
                            'longitude' => $collectorLocation->longitude,
                            'updated_at' => $collectorLocation->updated_at,
                        ] : null,
                    ],
                    'pickup_location' => [
                        'latitude' => $order->latitude,
                        'longitude' => $order->longitude,
                    ],
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la ubicación del recolector',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener todas las órdenes activas del usuario con ubicación del recolector
     */
    public function getActiveOrders(): JsonResponse
    {
        try {
            $token = request()->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token no proporcionado',
                ], 401);
            }

            $userId = $this->getUserIdFromToken($token);

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener el usuario',
                ], 401);
            }

            $typeWasteResponse = Http::baseUrl(config('services.waste_service.url'))
                ->withToken($token)
                ->get('/type-wastes');

            $typeWastes = collect($typeWasteResponse->json('data'))
                ->keyBy('id');

            $orders = Order::where('user_id', $userId)
                ->whereHas('state', function ($query) {
                    $query->where('name', State::SERVICE_START);
                })
                ->with([
                    'collectorOrder',
                    'state',
                    'orderTypeWastes',
                ])
                ->get()
                ->map(function ($order) use ($token, $typeWastes) {
                    $collectorLocation = null;
                    $collectorName = null;

                    if ($order->collectorOrder) {
                        $collectorId = $order->collectorOrder->collector_id;

                        $collectorLocation = CollectorLocation::where('collector_id', $collectorId)
                            ->latest('updated_at')
                            ->first();

                        try {
                            $collectorData = $this->authService->getCollectorById($collectorId, $token);
                            $collectorName = $collectorData['user_name'] ?? 'Recolector';
                        } catch (\Exception $e) {
                            $collectorName = 'Recolector';
                        }
                    }

                    return [
                        'id' => $order->id,
                        'collector' => $order->collectorOrder ? [
                            'id' => $order->collectorOrder->collector_id,
                            'name' => $collectorName,
                            'location' => $collectorLocation ? [
                                'latitude' => $collectorLocation->latitude,
                                'longitude' => $collectorLocation->longitude,
                                'updated_at' => $collectorLocation->updated_at,
                            ] : null,
                        ] : null,
                        'pickup_location' => [
                            'latitude' => $order->latitude,
                            'longitude' => $order->longitude,
                        ],
                        'status' => $order->state->name ?? 'pending',
                        'items' => $order->orderTypeWastes->map(function ($item) use ($typeWastes) {
                            $typeWaste = $typeWastes->get($item->type_waste_id);

                            return [
                                'type_waste_name' => $typeWaste['name'] ?? null,
                                'weight' => $item->weight,
                                'points' => $item->points,
                            ];
                        })->values(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $orders,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las órdenes activas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extraer el user_id del token JWT
     */
    private function getUserIdFromToken(string $token): ?int
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }

            $payload = json_decode(base64_decode($parts[1]), true);
            return $payload['sub'] ?? $payload['user_id'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obtener todas las órdenes reprogramadas del usuario
     */
    public function getRescheduleOrders(): JsonResponse
    {
        try {
            $token = request()->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token no proporcionado',
                ], 401);
            }

            $userId = $this->getUserIdFromToken($token);

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener el usuario',
                ], 401);
            }


            $orders = Order::where('user_id', $userId)
                ->whereHas('state', function ($query) {
                    $query->where('id', State::RESCHEDULED);
                })
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'status' => $order->state->name ?? 'pending',
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $orders,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las órdenes activas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
