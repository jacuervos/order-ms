<?php

namespace Tests\Feature\Order;

use App\Exceptions\FirebasePushException;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushNotificationConfigurationTest extends TestCase
{
    public function test_push_notification_service_configuration_keys_exist(): void
    {
        $config = config('services.push_notifications');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('firebase_project_id', $config);
        $this->assertArrayHasKey('firebase_credentials_json', $config);
        $this->assertArrayHasKey('firebase_credentials_path', $config);
    }

    public function test_push_notification_service_sends_fcm_message_with_cached_token(): void
    {
        $credentials = [
            'client_email' => 'firebase-adminsdk@test-project.iam.gserviceaccount.com',
            'private_key' => 'dummy-key',
        ];

        config()->set('services.push_notifications.firebase_project_id', 'green-city-test');
        config()->set('services.push_notifications.firebase_credentials_json', json_encode($credentials));
        config()->set('services.push_notifications.firebase_credentials_path', null);

        $cacheKey = 'firebase_push_access_token_' . hash('sha256', $credentials['client_email']);
        Cache::put($cacheKey, 'fake-google-oauth-token', now()->addMinutes(10));

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/green-city-test/messages:send' => Http::response([
                'name' => 'projects/green-city-test/messages/12345',
            ], 200),
        ]);

        $service = new PushNotificationService();
        $sent = $service->sendToToken(
            'device-fcm-token-123',
            'Nueva orden asignada',
            'Se te asignó la orden #10',
            ['order_id' => 10, 'type' => 'order_assigned']
        );

        $this->assertTrue($sent);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://fcm.googleapis.com/v1/projects/green-city-test/messages:send'
                && $request['message']['token'] === 'device-fcm-token-123'
                && $request['message']['notification']['title'] === 'Nueva orden asignada'
                && $request['message']['data']['order_id'] === '10';
        });
    }

    public function test_push_notification_throws_exception_when_not_configured(): void
    {
        config()->set('services.push_notifications.firebase_project_id', null);
        config()->set('services.push_notifications.firebase_credentials_json', null);
        config()->set('services.push_notifications.firebase_credentials_path', null);

        $this->expectException(FirebasePushException::class);

        $service = new PushNotificationService();
        $service->sendToToken('device-token', 'Titulo', 'Mensaje');
    }
}

