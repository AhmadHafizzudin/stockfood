<?php

use Illuminate\Support\Facades\Route;
use App\Services\LalamoveService;

Route::get('/test-lalamove-quotation', function () {
    try {
        $lalamoveService = new LalamoveService();
        
        // Test quotation data matching Postman collection format
        $quotationData = [
            'serviceType' => 'MOTORCYCLE',
            'specialRequests' => ['ROUND_TRIP'],
            'language' => 'en_MY',
            'stops' => [
                [
                    'coordinates' => [
                        'lat' => '3.028634',  // IOI Mall Puchong
                        'lng' => '101.616948'
                    ],
                    'address' => 'IOI Mall Puchong, Bandar Puchong Jaya, 47170 Puchong, Selangor'
                ],
                [
                    'coordinates' => [
                        'lat' => '3.051648',  // SetiaWalk
                        'lng' => '101.585165'
                    ],
                    'address' => 'SetiaWalk, Persiaran Wawasan, Pusat Bandar Puchong, 47160 Puchong, Selangor'
                ]
            ],
            'isRouteOptimized' => false,
            'item' => [
                'quantity' => '1',
                'weight' => 'LESS_THAN_3_KG',
                'categories' => ['FOOD_DELIVERY'],
                'handlingInstructions' => ['KEEP_UPRIGHT']
            ]
        ];
        
        $response = $lalamoveService->getQuotation($quotationData);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Lalamove quotation test completed',
            'request_data' => $quotationData,
            'response' => $response,
            'success' => $response && isset($response['data']['quotationId']),
            'quotation_id' => $response['data']['quotationId'] ?? null,
            'price' => [
                'total' => $response['data']['priceBreakdown']['total'] ?? null,
                'currency' => $response['data']['priceBreakdown']['currency'] ?? null
            ]
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Lalamove test failed',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

Route::get('/test-integration-summary', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Lalamove Integration Update Summary',
        'updates_completed' => [
            '✅ Updated LalamoveService signature generation to match Postman collection',
            '✅ Fixed getQuotation method to use proper request structure with data wrapper',
            '✅ Updated createOrder method to use quotation-based approach',
            '✅ Modified order_success function in helpers.php to use correct quotation format',
            '✅ Added proper item structure (quantity, weight, categories, handlingInstructions)',
            '✅ Added recipient information for order creation',
            '✅ Improved error handling and logging',
            '✅ Added lalamove_order_id column to orders table (already exists)'
        ],
        'key_changes' => [
            'Request structure now matches Postman collection exactly',
            'Proper HMAC signature generation with microsecond precision',
            'Quotation-first approach for order creation',
            'Enhanced error logging for debugging',
            'Non-blocking integration that won\'t fail order processing'
        ],
        'api_format' => [
            'endpoint' => '/v3/quotations',
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'hmac {key}:{timestamp}:{signature}',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Market' => 'MY'
            ],
            'body_structure' => [
                'data' => [
                    'serviceType' => 'MOTORCYCLE',
                    'specialRequests' => ['ROUND_TRIP'],
                    'language' => 'en_MY',
                    'stops' => '...',
                    'isRouteOptimized' => false,
                    'item' => [
                        'quantity' => '1',
                        'weight' => 'LESS_THAN_3_KG',
                        'categories' => ['FOOD_DELIVERY'],
                        'handlingInstructions' => ['KEEP_UPRIGHT']
                    ]
                ]
            ]
        ],
        'integration_flow' => [
            '1. Order payment successful → order_success() called',
            '2. Check if Lalamove service is enabled in settings',
            '3. Prepare quotation data with restaurant and delivery coordinates',
            '4. Call LalamoveService->createOrder() which gets quotation first',
            '5. If successful, save lalamove_order_id to order record',
            '6. Log success/failure for monitoring'
        ]
    ]);
});