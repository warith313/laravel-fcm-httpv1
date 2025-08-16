<?php

namespace Warith\Fcm;

/**
 * Class Fcm
 * @package Warith\Fcm
 */
class Fcm
{
    const ENDPOINT = 'https://fcm.googleapis.com/v1/projects/YOUR_PROJECT_ID/messages:send';

    protected $recipients;
    protected $topic;
    protected $data;
    protected $notification;
    protected $timeToLive;
    protected $priority;
    protected $package;

    protected $serviceAccountPath;
    protected $serviceAccount;
    protected $projectId;

    protected $responseLogEnabled = false;

    public function __construct()
    {
        $this->serviceAccountPath = env('FCM_SERVICE_ACCOUNT_PATH');
        
        // Ensure the service account path is correct
        if (!file_exists(storage_path($this->serviceAccountPath))) {
            throw new \Exception("Service account file not found at: " . storage_path($this->serviceAccountPath));
        }

        $this->serviceAccount = json_decode(file_get_contents(storage_path($this->serviceAccountPath)), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Error decoding JSON from service account file: ' . json_last_error_msg());
        }
        $this->projectId = $this->serviceAccount['project_id'] ?? null;
        if (!$this->projectId) {
            throw new \Exception('Project ID not found in service account file');
        }
    }

    public function to($recipients)
    {
        $this->recipients = $recipients;

        return $this;
    }

    public function toTopic($topic)
    {
        $this->topic = $topic;

        return $this;
    }

    public function data($data = [])
    {
        $this->data = $data;

        return $this;
    }

    public function notification($notification = [])
    {
        $this->notification = $notification;

        return $this;
    }

    public function priority(string $priority)
    {
        $this->priority = $priority;

        return $this;
    }

    public function timeToLive($timeToLive)
    {
        if ($timeToLive < 0) {
            $timeToLive = 0; // (0 seconds)
        }
        if ($timeToLive > 2419200) {
            $timeToLive = 2419200; // (28 days)
        }

        $this->timeToLive = $timeToLive;

        return $this;
    }

    public function setPackage($package)
    {
        $this->package = $package;

        return $this;
    }

    public function enableResponseLog($enable = true)
    {
        $this->responseLogEnabled = $enable;

        return $this;
    }

    private function getAccessToken()
    {
        // Prepare JWT claims for OAuth 2.0 token exchange
        $now = time();
        $jwtHeader = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $jwtPayload = json_encode([
            'iss' => $this->serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $this->serviceAccount['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600, // Token is valid for 1 hour
        ]);

        // Encode the header and payload
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($jwtHeader));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($jwtPayload));

        // Sign the JWT using the private key from the service account
        $signature = '';
        openssl_sign("$base64UrlHeader.$base64UrlPayload", $signature, $this->serviceAccount['private_key'], 'SHA256');
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        // Combine the encoded header, payload, and signature into a complete JWT
        $jwt = "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";

        // Exchange the JWT for an OAuth 2.0 access token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->serviceAccount['token_uri']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]));

        $response = curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response, true);

        if (isset($response['access_token'])) {
            return $response['access_token'];
        } else {
            throw new \Exception('Failed to obtain access token: ' . json_encode($response));
        }
    }

    public function send()
    {
        $imageLink = $this->notification['image'] ?? ($this->data['image'] ?? '');

        // Prepare the payload
        $payload = [
            'message' => [
                'data' => $this->data,
                'notification' => [
                    'title' => $this->notification['title'] ?? '',
                    'body' => $this->notification['body'] ?? '',
                    'image' => $imageLink,
                ],
            ],
        ];
    
        // Add timeToLive and package settings
        if ($this->timeToLive !== null && $this->timeToLive >= 0) {
            $payload['message']['android'] = ['ttl' => "{$this->timeToLive}s"];
        }
    
        if (!empty($this->package)) {
            $payload['message']['android']['restricted_package_name'] = $this->package;
        }

        // Apply Android and APNS overrides
        $payloads['message']['android'] = array_merge(
            $payload['message']['android'] ?? [],
            [
                'notification' => [
                    'click_action' => $this->notification['click_action'] ?? 'android.intent.action.MAIN'
                ]
            ]
        );

        $payloads['message']['apns'] = [
            'payload' => [
                'aps' => [
                    'badge' => 1,
                    'mutable-content' => !empty($imageLink) ? 1 : 0,
                    'sound' => $this->notification['sound'] ?? 'default'
                ]
            ],
            'fcm_options' => [
                'image' => !empty($imageLink) ? $imageLink : null
            ]
        ];

        $payloads['message']['webpush'] = [
            'headers' => [
                'image' => !empty($imageLink) ? $imageLink : null
            ]
        ];
        
        $payload['message']['android'] = $payloads['message']['android'] ?? [];
        $payload['message']['apns'] = $payloads['message']['apns'] ?? [];
        $payload['message']['webpush'] = $payloads['message']['webpush'] ?? [];
    
        $headers = [
            'Authorization: Bearer ' . $this->getAccessToken(),
            'Content-Type: application/json',
        ];
    
        $endpoint = str_replace('YOUR_PROJECT_ID', $this->projectId, self::ENDPOINT);
    
        $responses = [];

        if ($this->topic) {
            // Sending to a topic
            $payload['message']['topic'] = $this->topic;
    
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            $response = curl_exec($ch);
    
            if ($this->responseLogEnabled) {
                logger('laravel-fcm', ['response' => $response]);
            }
    
            $responses[] = json_decode($response, true);
            curl_close($ch);
    
        } elseif (is_array($this->recipients)) {
            // Sending to multiple tokens
            foreach ($this->recipients as $recipient) {
                $payload['message']['token'] = $recipient;
    
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $endpoint);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, CURL_IPRESOLVE_V4);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                $response = curl_exec($ch);
    
                if ($this->responseLogEnabled) {
                    logger('laravel-fcm', ['response' => $response]);
                }
    
                $responses[] = json_decode($response, true);
                curl_close($ch);
            }
        } elseif ($this->recipients) {
            // Sending to a single token
            $payload['message']['token'] = $this->recipients;
    
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            $response = curl_exec($ch);
    
            if ($this->responseLogEnabled) {
                logger('laravel-fcm', ['response' => $response]);
            }
    
            $responses[] = json_decode($response, true);
            curl_close($ch);
        }
    
        return $responses;
    }
}
