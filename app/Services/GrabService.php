<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GrabService
{
    private $baseUrl;
    private $clientId;
    private $clientSecret;

    public function __construct()
    {
        $this->baseUrl = config('services.grab.sandbox_url'); // Switch to production URL when live
        $this->clientId = config('services.grab.client_id');
        $this->clientSecret = config('services.grab.client_secret');
    }

    private function getAccessToken()
    {
        try {
            $response = Http::asForm()->post($this->baseUrl . '/oauth2/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials'
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['access_token'])) {
                return $data['access_token'];
            }

            Log::error('Grab Auth Error', ['response' => $data]);
            return null;
        } catch (\Exception $e) {
            Log::error('Grab Auth Exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    public function createDelivery($order)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Unable to retrieve Grab access token'];
        }

        try {
            // Parse delivery address from JSON
            $deliveryAddress = json_decode($order->delivery_address, true);
            
            $response = Http::withToken($token)->post($this->baseUrl . '/delivery/v1/deliveries', [
                'service_type' => 'INSTANT',
                'stops' => [
                    [
                        'address' => $order->restaurant->address,
                        'coordinates' => [
                            'latitude' => $order->restaurant->latitude,
                            'longitude' => $order->restaurant->longitude
                        ]
                    ],
                    [
                        'address' => $deliveryAddress['address'] ?? '',
                        'coordinates' => [
                            'latitude' => $deliveryAddress['latitude'] ?? 0,
                            'longitude' => $deliveryAddress['longitude'] ?? 0
                        ]
                    ]
                ],
                'payment_method' => 'CASHLESS',
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            Log::error('Grab CreateDelivery Error', ['response' => $response->json()]);
            return [
                'success' => false,
                'message' => $response->json()['message'] ?? 'Grab create delivery failed'
            ];
        } catch (\Exception $e) {
            Log::error('Grab CreateDelivery Exception', ['message' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getDeliveryStatus($deliveryId)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Unable to retrieve Grab access token'];
        }

        try {
            $response = Http::withToken($token)->get($this->baseUrl . "/delivery/v1/deliveries/{$deliveryId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            Log::error('Grab GetDeliveryStatus Error', ['response' => $response->json()]);
            return [
                'success' => false,
                'message' => $response->json()['message'] ?? 'Unable to fetch Grab delivery status'
            ];
        } catch (\Exception $e) {
            Log::error('Grab GetDeliveryStatus Exception', ['message' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function cancelDelivery($deliveryId)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'message' => 'Unable to retrieve Grab access token'];
        }

        try {
            $response = Http::withToken($token)->post($this->baseUrl . "/delivery/v1/deliveries/{$deliveryId}/cancel");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            Log::error('Grab CancelDelivery Error', ['response' => $response->json()]);
            return [
                'success' => false,
                'message' => $response->json()['message'] ?? 'Unable to cancel Grab delivery'
            ];
        } catch (\Exception $e) {
            Log::error('Grab CancelDelivery Exception', ['message' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}