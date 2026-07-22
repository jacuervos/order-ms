<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PickupPoint;
use App\Services\AuthMicroserviceService;
use Illuminate\Http\JsonResponse;

class CollectorPickupPointsController extends Controller
{
    private AuthMicroserviceService $authService;

    public function __construct(AuthMicroserviceService $authService)
    {
        $this->authService = $authService;
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

            $collectorId = $collectorData['id'];

            // Obtener las órdenes activas asignadas al recolector a través de collector_orders
            $orders = Order::whereHas('collectorOrder', function ($query) use ($collectorId) {
                $query->where('collector_id', $collectorId);
            })
                ->whereNull('completed_at')
                ->with('pickupPoints')
                ->get();

            // Compilar todos los puntos de recogida
            $pickupPoints = PickupPoint::whereIn('order_id', $orders->pluck('id'))
                ->where(function ($query) {
                    $query->whereNull('completed_at')
                        ->orWhere('completed_at', '>', now()->subHours(24));
                })
                ->with('order')
                ->get()
                ->map(fn($point) => [
                    'id' => $point->id,
                    'order_id' => $point->order_id,
                    'latitude' => $point->latitude,
                    'longitude' => $point->longitude,
                    'address' => $point->address,
                    'notes' => $point->notes,
                    'completed_at' => $point->completed_at,
                    'user_name' => $collectorData['user_name'] ?? 'Usuario',
                    'user_phone' => $collectorData['user_phone'] ?? null,
                ]);

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
     * Marcar un punto de recogida como completado
     */
    public function completePickupPoint(PickupPoint $pickupPoint): JsonResponse
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

            $collectorId = $collectorData['id'];

            // Verificar que el punto pertenece a una orden del recolector
            $order = $pickupPoint->order;
            $isAssigned = $order->collectorOrder()->where('collector_id', $collectorId)->exists();

            if (!$isAssigned) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este punto no está asignado a tu recolección',
                ], 403);
            }

            $pickupPoint->update(['completed_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Punto de recogida marcado como completado',
                'data' => $pickupPoint,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar el punto como completado',
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

            $collectorId = $collectorData['id'];

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
                    ],
                    'pickup_points' => $order->pickupPoints
                        ->map(fn($point) => [
                            'id' => $point->id,
                            'latitude' => $point->latitude,
                            'longitude' => $point->longitude,
                            'address' => $point->address,
                            'notes' => $point->notes,
                            'completed_at' => $point->completed_at,
                        ]),
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
}
