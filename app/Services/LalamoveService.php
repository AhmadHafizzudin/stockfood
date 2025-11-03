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
        $body = !empty($data) ? json_encode($data) : '';

        $auth = $this->generateSignature($method, $path, $body);

        $headers = [
            'Authorization' => "hmac {$this->apiKey}:{$auth['timestamp']}:{$auth['signature']}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Market' => 'MY',  // Malaysia market code
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
        
        // Structure the request body exactly like the Postman collection
        $requestBody = [
            'data' => [
                'serviceType' => $data['serviceType'] ?? 'MOTORCYCLE',
                'specialRequests' => $data['specialRequests'] ?? [],
                'language' => $data['language'] ?? 'en_MY',
                'stops' => $data['stops'] ?? [],
                'isRouteOptimized' => $data['isRouteOptimized'] ?? false,
                'item' => [
                    'quantity' => $data['item']['quantity'] ?? '1',
                    'weight' => $data['item']['weight'] ?? 'LESS_THAN_3_KG',
                    'categories' => $data['item']['categories'] ?? ['FOOD_DELIVERY'],
                    'handlingInstructions' => $data['item']['handlingInstructions'] ?? ['KEEP_UPRIGHT']
                ]
            ]
        ];
        
        $body = json_encode($requestBody);
        
        return $this->makeRequest('POST', $path, $body);
    }

    /**
     * Create order with Lalamove (using quotation-based approach)
     */
    public function createOrder($quotationData, $quotationId = null)
    {
        // If no quotationId provided, get quotation first
        if (!$quotationId) {
            $quotationResponse = $this->getQuotation($quotationData);
            
            if (!$quotationResponse || !isset($quotationResponse['data']['quotationId'])) {
                throw new \Exception('Failed to get quotation for order creation');
            }
            
            $quotationId = $quotationResponse['data']['quotationId'];
        }
        
        $path = '/v3/orders';
        
        // Structure the order request body
        $requestBody = [
            'data' => [
                'quotationId' => $quotationId,
                'sender' => [
                    'stopId' => $quotationResponse['data']['stops'][0]['stopId'] ?? null,
                    'name' => config('app.name', 'StockFood'),
                    'phone' => '+60123456789' // Should be from restaurant or config
                ],
                'recipients' => [
                    [
                        'stopId' => $quotationResponse['data']['stops'][1]['stopId'] ?? null,
                        'name' => $quotationData['recipient']['name'] ?? 'Customer',
                        'phone' => $quotationData['recipient']['phone'] ?? '+60123456789'
                    ]
                ]
            ]
        ];
        
        $body = json_encode($requestBody);
        
        return $this->makeRequest('POST', $path, $body);
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
            $response = $this->makeRequest('PUT', "/{$this->version}/orders/{$orderId}/cancel");

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