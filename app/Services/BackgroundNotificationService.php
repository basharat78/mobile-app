<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BackgroundNotificationService
{
    /**
     * Send a background push notification to a user via FCM V1 API.
     */
    public static function send($user, string $title, string $body, array $data = [])
    {
        if (!$user->fcm_token) {
            return false;
        }

        $credentialsPath = base_path(env('FIREBASE_CREDENTIALS_PATH', 'storage/app/firebase-auth.json'));

        if (!file_exists($credentialsPath)) {
            Log::error("BackgroundNotification: Credentials file not found at {$credentialsPath}");
            return false;
        }

        try {
            $credentials = json_decode(file_get_contents($credentialsPath), true);
            $accessToken = self::generateAccessToken($credentials);

            if (!$accessToken) {
                return false;
            }

            $projectId = $credentials['project_id'];
            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

            $response = Http::withToken($accessToken)->post($url, [
                'message' => [
                    'token' => $user->fcm_token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => array_map('strval', array_merge($data, [
                        'title' => $title,
                        'body' => $body,
                    ])),
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'sound' => 'default',
                            'click_action' => 'TOP_STORY_ACTIVITY',
                        ],
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'alert' => [
                                    'title' => $title,
                                    'body' => $body,
                                ],
                                'sound' => 'default',
                            ],
                        ],
                    ],
                ],
            ]);

            if ($response->successful()) {
                Log::info("BackgroundNotification: V1 Success for User #{$user->id}");
                return true;
            }

            Log::error("BackgroundNotification: V1 Failure for User #{$user->id}. " . $response->body());
            return false;

        } catch (\Exception $e) {
            Log::error("BackgroundNotification: V1 Exception - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate a Google OAuth2 Access Token using the Service Account JSON.
     * This is a self-contained implementation to avoid heavy dependencies.
     */
    private static function generateAccessToken($credentials)
    {
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        
        $now = time();
        $payload = json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]);

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);

        $signature = '';
        $success = openssl_sign(
            $base64UrlHeader . "." . $base64UrlPayload,
            $signature,
            $credentials['private_key'],
            'sha256WithRSAEncryption'
        );

        if (!$success) {
            Log::error("BackgroundNotification: OpenSSL signing failed.");
            return null;
        }

        $base64UrlSignature = self::base64UrlEncode($signature);
        $jwt = $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        return $response->json('access_token');
    }

    private static function base64UrlEncode($data)
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
}
