<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\State;
use App\Services\AuthMicroserviceService;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CollectorAcceptOrderController extends Controller
{
    private AuthMicroserviceService $authService;
    private PushNotificationService $pushNotificationService;

    public function __construct(AuthMicroserviceService $authService, PushNotificationService $pushNotificationService)
    {
        $this->authService = $authService;
        $this->pushNotificationService = $pushNotificationService;
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

            try {
                $userId = data_get($collectorData, 'user_id')
                    ?? data_get($collectorData, 'user.id')
                    ?? data_get($collectorData, 'id_user');

                $userData = null;

                if ($userId !== null && $userId !== '') {
                    $userData = $this->authService->getUserById((int) $userId, $token);
                }

                $collectorToken = $this->resolvePushToken($collectorData, $userData);

                if ($collectorToken) {
                    $this->pushNotificationService->sendToToken(
                        $collectorToken,
                        'Orden aceptada',
                        'El recolector aceptó la orden #' . $order->id,
                        [
                            'type' => 'order_accepted',
                            'order_id' => (string) $order->id,
                            'collector_id' => (string) $collectorId,
                            'state' => (string) $serviceStartState->id,
                        ]
                    );
                }
            } catch (\Throwable $pushException) {
                Log::warning('La orden se aceptó, pero falló la notificación push al recolector', [
                    'collector_id' => $collectorId,
                    'order_id' => $order->id,
                    'error' => $pushException->getMessage(),
                ]);
            }

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
