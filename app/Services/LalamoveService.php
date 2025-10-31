<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LalamoveService
{
    private $apiKey;
    private $secret;
    private $baseUrl;
    private $version;

    public function __construct()
    {
        $this->apiKey = config('lalamove.api_key');
        $this->secret = config('lalamove.secret');
        $this->baseUrl = config('lalamove.base_url');
        $this->version = config('lalamove.version');
    }

    /**
     * Generate HMAC signature for Lalamove API authentication
     * Following the exact implementation from Postman pre-request script
     */
    private function generateSignature($method, $path, $body = '', $timestamp = null)
    {
        if (!$timestamp) {
            $timestamp = (string) (round(microtime(true) * 1000)); // Current timestamp in milliseconds
        }

        // Trim body and ensure it's not null
        $body = $body ?: "";
        $body = trim($body);

        // Construct message to sign exactly as in Postman script
        $message = $timestamp . "\r\n" . strtoupper($method) . "\r\n" . $path . "\r\n\r\n" . $body;

        // Generate HMAC SHA256 signature and convert to hex (lowercase)
        $signature = hash_hmac('sha256', $message, $this->secret);

        Log::info('Lalamove Signature Debug', [
            'timestamp' => $timestamp,
            'method' => strtoupper($method),
            'path' => $path,
            'body' => $body,
            'message' => $message,
            'signature' => $signature
        ]);

        return [
            'timestamp' => $timestamp,
            'signature' => $signature
        ];
    }

    /**
     * Make authenticated request to Lalamove API
     */
    private function makeRequest($method, $endpoint, $data = [])
    {
        $path = "/{$this->version}" . $endpoint;
        $url = $this->baseUrl . $path;
        $body = !empty($data) ? json_encode($data) : '';

        $auth = $this->generateSignature($method, $path, $body);

        $headers = [
            'Authorization' => "hmac {$this->apiKey}:{$auth['timestamp']}:{$auth['signature']}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        Log::info('Lalamove API Request', [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body
        ]);

        $response = Http::withHeaders($headers)
            ->timeout(config('lalamove.timeout.request', 60))
            ->connectTimeout(config('lalamove.timeout.connect', 30));

        switch (strtoupper($method)) {
            case 'GET':
                $response = $response->get($url);
                break;
            case 'POST':
                $response = $response->withBody($body, 'application/json')->post($url);
                break;
            case 'PUT':
                $response = $response->withBody($body, 'application/json')->put($url);
                break;
            case 'DELETE':
                $response = $response->delete($url);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported HTTP method: {$method}");
        }

        Log::info('Lalamove API Response', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return $response;
    }

    /**
     * Get quotation for delivery
     */
    public function getQuotation($quotationData)
    {
        try {
            $response = $this->makeRequest('POST', '/quotations', $quotationData);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => $response->json(),
                'status' => $response->status()
            ];
        } catch (\Exception $e) {
            Log::error('Lalamove Quotation Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create an order
     */
    public function createOrder($orderData)
    {
        try {
            $response = $this->makeRequest('POST', '/orders', $orderData);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => $response->json(),
                'status' => $response->status()
            ];
        } catch (\Exception $e) {
            Log::error('Lalamove Order Creation Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get order details
     */
    public function getOrder($orderId)
    {
        try {
            $response = $this->makeRequest('GET', "/orders/{$orderId}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => $response->json(),
                'status' => $response->status()
            ];
        } catch (\Exception $e) {
            Log::error('Lalamove Get Order Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancel an order
     */
    public function cancelOrder($orderId)
    {
        try {
            $response = $this->makeRequest('PUT', "/orders/{$orderId}/cancel");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => $response->json(),
                'status' => $response->status()
            ];
        } catch (\Exception $e) {
            Log::error('Lalamove Cancel Order Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Helper method to format quotation data
     */
    public function formatQuotationData($serviceType, $stops, $language = 'en_MY')
    {
        return [
            'data' => [
                'serviceType' => $serviceType,
                'stops' => $stops,
                'language' => $language
            ]
        ];
    }

    /**
     * Helper method to format stop data
     */
    public function formatStop($lat, $lng, $address, $contactName = null, $contactPhone = null)
    {
        $stop = [
            'coordinates' => [
                'lat' => (string) $lat,
                'lng' => (string) $lng
            ],
            'address' => $address
        ];

        if ($contactName) {
            $stop['contactName'] = $contactName;
        }

        if ($contactPhone) {
            $stop['contactPhone'] = $contactPhone;
        }

        return $stop;
    }
}