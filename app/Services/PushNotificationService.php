<?php

namespace App\Services;

use App\Exceptions\FirebasePushException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class PushNotificationService
{
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        $projectId = config('services.push_notifications.firebase_project_id');
        $credentials = $this->resolveCredentials();

        if (!$projectId || !$credentials) {
            throw new FirebasePushException('Firebase credentials or project id are not configured.');
        }

        $accessToken = $this->getAccessToken($credentials);
        $endpoint = sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', $projectId);

        $response = Http::withToken($accessToken)->post($endpoint, [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_map(static fn ($value) => (string) $value, $data),
            ],
        ]);

        if (!$response->successful()) {
            throw new RequestException($response);
        }

        return true;
    }

    private function resolveCredentials(): ?array
    {
        $path = config('services.push_notifications.firebase_credentials_path');
        $json = config('services.push_notifications.firebase_credentials_json');

        if (is_string($path) && $path !== '' && is_file($path)) {
            $contents = file_get_contents($path);

            if (is_string($contents) && $contents !== '') {
                $decoded = json_decode($contents, true);

                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        if (is_string($json) && $json !== '') {
            $decoded = json_decode($json, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function getAccessToken(array $credentials): string
    {
        $cacheKey = 'firebase_push_access_token_' . hash('sha256', $credentials['client_email'] ?? 'default');

        return Cache::remember($cacheKey, now()->addMinutes(55), function () use ($credentials) {
            foreach (['client_email', 'private_key'] as $key) {
                if (empty($credentials[$key])) {
                    throw new FirebasePushException("Missing Firebase credential field: {$key}");
                }
            }

            $jwt = $this->buildJwt($credentials['client_email'], $credentials['private_key']);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (!$response->successful()) {
                throw new RequestException($response);
            }

            $accessToken = $response->json('access_token');

            if (!is_string($accessToken) || $accessToken === '') {
                throw new FirebasePushException('Firebase access token was not returned by Google.');
            }

            return $accessToken;
        });
    }

    private function buildJwt(string $clientEmail, string $privateKey): string
    {
        $issuedAt = time();
        $payload = [
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
        ];

        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($payload)),
        ];

        $signingInput = implode('.', $segments);

        $signature = '';
        $signed = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (!$signed) {
            throw new FirebasePushException('Unable to sign Firebase JWT.');
        }

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
