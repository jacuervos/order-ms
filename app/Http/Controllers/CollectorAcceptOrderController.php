<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\State;
use App\Services\AuthMicroserviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CollectorAcceptOrderController extends Controller
{
    private AuthMicroserviceService $authService;

    public function __construct(AuthMicroserviceService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Aceptar orden y cambiar estado a "Inicio de servicio"
     * 
     * @param Order $order
     * @return JsonResponse
     */
    public function __invoke(Order $order): JsonResponse
    {
        DB::beginTransaction();

        try {
            $token = request()->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token no proporcionado',
                ], 401);
            }

            // Obtener datos del recolector autenticado
            $collectorData = $this->authService->getAuthenticatedCollector($token);

            if (!$collectorData) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no es un recolector',
                ], 403);
            }

            $collectorId = $collectorData['id'];

            // Verificar que la orden esté asignada al recolector
            $collectorOrder = $order->collectorOrder;

            if (!$collectorOrder || $collectorOrder->collector_id != $collectorId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta orden no está asignada a ti',
                ], 403);
            }

            // Verificar que la orden esté en estado "Asignada a recolector"
            $assignedState = State::where('name', State::ASSIGNED_TO_COLLECTOR)->first();
            
            if ($order->state_id !== $assignedState->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'La orden no está en estado correcto para ser aceptada',
                ], 400);
            }

            // Cambiar estado a "Inicio de servicio"
            $serviceStartState = State::where('name', State::SERVICE_START)->first();
            
            $order->update([
                'state_id' => $serviceStartState->id,
                'service_started_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Orden aceptada. Iniciando servicio',
                'data' => [
                    'order_id' => $order->id,
                    'state' => $serviceStartState->name,
                    'service_started_at' => $order->service_started_at,
                ],
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error al aceptar la orden',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
