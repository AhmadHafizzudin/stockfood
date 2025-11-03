<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\LalamoveService;

echo "=== Testing Updated Lalamove Integration ===\n\n";

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
    
    echo "Testing Lalamove Quotation...\n";
    echo "Request Data: " . json_encode($quotationData, JSON_PRETTY_PRINT) . "\n\n";
    
    $response = $lalamoveService->getQuotation($quotationData);
    
    echo "Response Status: " . ($response ? 'Success' : 'Failed') . "\n";
    echo "Response Data: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
    
    if ($response && isset($response['data']['quotationId'])) {
        echo "✅ Quotation successful!\n";
        echo "Quotation ID: " . $response['data']['quotationId'] . "\n";
        echo "Total Price: " . ($response['data']['priceBreakdown']['total'] ?? 'N/A') . " " . ($response['data']['priceBreakdown']['currency'] ?? '') . "\n";
    } else {
        echo "❌ Quotation failed!\n";
        if (isset($response['error'])) {
            echo "Error: " . json_encode($response['error'], JSON_PRETTY_PRINT) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Exception occurred: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";