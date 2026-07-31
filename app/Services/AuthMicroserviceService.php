<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class AuthMicroserviceService
{
    private string $baseUrl;
    private string $baseUrlUser;

    public function __construct()
    {
        $this->baseUrl = env('AUTH_MS_URL', 'http://ud-ms-auth:8000');
        $this->baseUrlUser = env('USER_URL', 'http://ud-ms-auth:8000');
    }

    /**
     * Obtener el collector del usuario autenticado
     * @throws Exception
     */
    public function getAuthenticatedCollector(string $token): ?array
    {
        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->get("{$this->baseUrl}/auth_me");
            if ($response->successful()) {
                return $response->json('data');
            }

            return null;
        } catch (Exception $e) {
            throw new Exception("Error comunicando con Auth MS: {$e->getMessage()}");
        }
    }

    /**
     * Obtener datos del usuario por ID
     * @throws Exception
     */
    public function getUserById(int $userId, string $token): ?array
    {
        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->get("{$this->baseUrlUser}/user/{$userId}");

            if ($response->successful()) {
                return $response->json('data');
            }

            return null;
        } catch (Exception $e) {
            throw new Exception("Error obteniendo usuario: {$e->getMessage()}");
        }
    }

    /**
     * Obtener datos del collector por ID
     * @throws Exception
     */
    public function getCollectorById(int $collectorId, string $token): ?array
    {
        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->get("{$this->baseUrl}/api/collectors/{$collectorId}");

            if ($response->successful()) {
                return $response->json('data');
            }

            return null;
        } catch (Exception $e) {
            throw new Exception("Error obteniendo collector: {$e->getMessage()}");
        }
    }
}
