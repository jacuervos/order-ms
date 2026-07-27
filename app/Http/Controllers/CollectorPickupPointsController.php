<?php

namespace App\Http\Controllers;

use App\Models\Order;
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

            $collectorId = $collectorData['collector']['id'];

            // Obtener las órdenes activas asignadas al recolector a través de collector_orders
            $orders = Order::whereHas('collectorOrder', function ($query) use ($collectorId) {
                $query->where('collector_id', $collectorId);
            })->get();

            // Compilar todos los puntos de recogida desde las órdenes
            $pickupPoints = $orders->map(fn($order) => [
                'id' => $order->id,
                'order_id' => $order->id,
                'latitude' => (float) $order->latitude,
                'longitude' => (float) $order->longitude
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

            return response()->json([
                'success' => true,
                'message' => 'Orden marcada como completada',
                'data' => $order,
            ], 200);

        } catch (\Exception $e) {
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
}
