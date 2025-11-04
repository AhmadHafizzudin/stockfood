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
    private $market;

    public function __construct()
    {
        $this->apiKey = config('lalamove.api_key');
        $this->secret = config('lalamove.secret');
        $this->baseUrl = rtrim((function ($url) {
            // Normalize base URL: ensure protocol and no trailing slash
            if (!$url) return '';
            if (stripos($url, 'http://') !== 0 && stripos($url, 'https://') !== 0) {
                $url = 'https://' . ltrim($url, '/');
            }
            return $url;
        })(config('lalamove.base_url')), '/');
        $this->version = config('lalamove.version');
        $this->market = config('lalamove.market', 'HK');
    }

    /**
     * Generate HMAC signature for authentication (matching Postman collection format)
     */
    private function generateSignature($method, $path, $body)
    {
        $timestamp = (int)(microtime(true) * 1000); // Convert to milliseconds with precision
        $message = "{$timestamp}\r\n{$method}\r\n{$path}\r\n\r\n{$body}";
        $signature = hash_hmac('sha256', $message, $this->secret);

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
        $path = $endpoint;
        $url = $this->baseUrl . $path;
        // Allow passing either an already-encoded JSON string or an array
        if (is_string($data)) {
            $body = $data;
        } elseif (!empty($data)) {
            $body = json_encode($data);
        } else {
            $body = '';
        }

        $auth = $this->generateSignature($method, $path, $body);

        $headers = [
            'Authorization' => "hmac {$this->apiKey}:{$auth['timestamp']}:{$auth['signature']}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Market' => $this->market,
        ];

        Log::info('Lalamove API Request', [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body
        ]);

        try {
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
                case 'PATCH':
                    $response = $response->withBody($body, 'application/json')->patch($url);
                    break;
                case 'DELETE':
                    $response = $response->delete($url);
                    break;
                default:
                    throw new \InvalidArgumentException("Unsupported HTTP method: {$method}");
            }

            Log::info('Lalamove API Response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body(),
                'successful' => $response->successful()
            ]);
            
            // Log specific error details if request failed
            if (!$response->successful()) {
                Log::error('Lalamove API Request Failed', [
                    'status' => $response->status(),
                    'error_body' => $response->body(),
                    'url' => $url,
                    'method' => $method,
                    'request_headers' => $headers,
                    'request_data' => $data
                ]);
            }
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Lalamove API Request Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'url' => $url,
                'method' => $method,
                'headers' => $headers,
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Get quotation from Lalamove API (matching Postman collection format)
     */
    public function getQuotation($data)
    {
        $path = '/v3/quotations';

        // Ensure body follows Postman structure and includes required defaults
        $incoming = is_array($data) && isset($data['data']) ? $data['data'] : $data;
        $merged = [
            'serviceType' => $incoming['serviceType'] ?? 'MOTORCYCLE',
            'language' => $incoming['language'] ?? 'en_MY',
            'stops' => $incoming['stops'] ?? [],
            'isRouteOptimized' => $incoming['isRouteOptimized'] ?? false,
            'item' => [
                'quantity' => ($incoming['item']['quantity'] ?? '1'),
                'weight' => ($incoming['item']['weight'] ?? 'LESS_THAN_3_KG'),
                'categories' => ($incoming['item']['categories'] ?? ['FOOD_DELIVERY']),
                'handlingInstructions' => ($incoming['item']['handlingInstructions'] ?? ['KEEP_UPRIGHT'])
            ]
        ];
        // Preserve any extra keys such as specialRequests
        if (isset($incoming['specialRequests'])) {
            $merged['specialRequests'] = $incoming['specialRequests'];
        }
        $requestBody = ['data' => $merged];

        $response = $this->makeRequest('POST', $path, $requestBody);

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
    }

    /**
     * Create order with Lalamove (using quotation-based approach)
     */
    public function createOrder($orderData)
    {
        $path = '/v3/orders';

        // Expect fully formed Postman-style payload: { data: { quotationId, sender, recipients, ... } }
        $payload = is_array($orderData) && isset($orderData['data']) ? $orderData : ['data' => $orderData];

        $response = $this->makeRequest('POST', $path, $payload);

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
    }

    /**
     * Get order details
     */
    public function getOrder($orderId)
    {
        try {
            $response = $this->makeRequest('GET', "/{$this->version}/orders/{$orderId}");

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
            // Align to Postman: DELETE /v3/orders/{orderId}
            $response = $this->makeRequest('DELETE', "/{$this->version}/orders/{$orderId}");

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
     * Get quotation details
     */
    public function getQuotationDetails($quotationId)
    {
        try {
            $response = $this->makeRequest('GET', "/{$this->version}/quotations/{$quotationId}");

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
            Log::error('Lalamove Get Quotation Details Error', [
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
     * Stub: Add priority fee
     */
    public function addPriorityFee($orderId, $payload = [])
    {
        // Housing function per request; to be implemented later
        return [
            'success' => false,
            'message' => 'addPriorityFee not implemented yet'
        ];
    }

    /**
     * Stub: Edit order
     */
    public function editOrder($orderId, $payload = [])
    {
        // Housing function per request; to be implemented later
        return [
            'success' => false,
            'message' => 'editOrder not implemented yet'
        ];
    }

    // Note: removed previous stub of updateWebhook to avoid redeclaration.

    /**
     * Helper method to format quotation data
     */
    public function formatQuotationData($serviceType, $stops, $language = 'en_MY')
    {
        return [
            'data' => [
                'serviceType' => $serviceType,
                'language' => $language,
                'stops' => $stops,
                'isRouteOptimized' => false,
                'item' => [
                    'quantity' => '1',
                    'weight' => 'LESS_THAN_3_KG',
                    'categories' => ['FOOD_DELIVERY'],
                    'handlingInstructions' => ['KEEP_UPRIGHT']
                ]
            ]
        ];
    }

    /**
     * Helper method to format stop data
     */
    public function formatStop($lat, $lng, $address, $contactName = null, $contactPhone = null)
    {
        // Clamp and round coordinates to satisfy Lalamove pattern (max 15 decimals)
        $latNum = max(-90, min(90, (float) $lat));
        $lngNum = max(-180, min(180, (float) $lng));
        // Use up to 6 decimals for stability and payload size
        $latStr = rtrim(rtrim(number_format($latNum, 6, '.', ''), '0'), '.');
        $lngStr = rtrim(rtrim(number_format($lngNum, 6, '.', ''), '0'), '.');

        // Align with Lalamove v3: stops should only include coordinates and address
        return [
            'coordinates' => [
                'lat' => $latStr,
                'lng' => $lngStr
            ],
            'address' => $address
        ];
    }

    /**
     * Update webhook URL for Lalamove callbacks
     */
    public function updateWebhook(string $url)
    {
        $path = '/v3/webhook';
        $payload = ['data' => ['url' => $url]];
        $response = $this->makeRequest('PATCH', $path, $payload);
        if ($response->successful()) {
            return ['success' => true, 'data' => $response->json()];
        }
        return ['success' => false, 'error' => $response->json(), 'status' => $response->status()];
    }
}