<?php

namespace App\Http\Controllers;

use App\Models\CollectorLocation;
use App\Services\AuthMicroserviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateCollectorLocationController extends Controller
{
    private AuthMicroserviceService $authService;

    public function __construct(AuthMicroserviceService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Actualizar la ubicación actual del recolector
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();

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

            // Validar datos
            $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
            ]);

            // Actualizar o crear la ubicación del recolector
            $collectorLocation = CollectorLocation::updateOrCreate(
                ['collector_id' => $collectorId],
                [
                    'latitude' => $request->input('latitude'),
                    'longitude' => $request->input('longitude'),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Ubicación actualizada correctamente',
                'data' => [
                    'collector_id' => $collectorLocation->collector_id,
                    'latitude' => $collectorLocation->latitude,
                    'longitude' => $collectorLocation->longitude,
                    'updated_at' => $collectorLocation->updated_at,
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la ubicación',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
