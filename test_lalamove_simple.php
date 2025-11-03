<?php

// Simple test script for Lalamove API without market parameter
require_once 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Test configuration
echo "=== Lalamove API Test (No Market) ===\n";
echo "API Key: " . ($_ENV['LALAMOVE_API_KEY'] ? 'Set (' . substr($_ENV['LALAMOVE_API_KEY'], 0, 10) . '...)' : 'Not Set') . "\n";
echo "Secret: " . ($_ENV['LALAMOVE_SECRET'] ? 'Set (' . substr($_ENV['LALAMOVE_SECRET'], 0, 10) . '...)' : 'Not Set') . "\n";
echo "Base URL: " . $_ENV['LALAMOVE_BASE_URL'] . "\n";
echo "Version: " . $_ENV['LALAMOVE_VERSION'] . "\n\n";

if (!$_ENV['LALAMOVE_API_KEY'] || !$_ENV['LALAMOVE_SECRET']) {
    echo "❌ API Key or Secret not configured!\n";
    exit(1);
}

// Test HMAC signature generation
function generateSignature($method, $path, $body, $timestamp, $apiKey, $secret) {
    $message = $timestamp . "\r\n" . $method . "\r\n" . $path . "\r\n\r\n" . trim($body);
    $signature = hash_hmac('sha256', $message, $secret);
    
    echo "Debug Info:\n";
    echo "Timestamp: $timestamp\n";
    echo "Method: $method\n";
    echo "Path: $path\n";
    echo "Body: " . trim($body) . "\n";
    echo "Message: " . str_replace(["\r\n"], ['\\r\\n'], $message) . "\n";
    echo "Signature: $signature\n\n";
    
    return $signature;
}

// Test quotation data (market sent as header only)
$quotationData = [
    'data' => [
        'serviceType' => 'MOTORCYCLE',
        'stops' => [
            [
                'coordinates' => [
                    'lat' => '3.048593',
                    'lng' => '101.671568'
                ],
                'address' => 'MATAHARI Bukit Jalil, No 2-1, Jalan Jalil 1, Lebuhraya Bukit Jalil, Sungai Besi, 57000 Kuala Lumpur, Malaysia'
            ],
            [
                'coordinates' => [
                    'lat' => '2.754873',
                    'lng' => '101.703744'
                ],
                'address' => '64000 Sepang, Selangor, Malaysia'
            ]
        ],
        'language' => 'en_MY'
    ]
];

$body = json_encode($quotationData);
$timestamp = time() * 1000; // milliseconds
$method = 'POST';
$path = '/' . $_ENV['LALAMOVE_VERSION'] . '/quotations';
$url = $_ENV['LALAMOVE_BASE_URL'] . $path;

// Generate signature
$signature = generateSignature($method, $path, $body, $timestamp, $_ENV['LALAMOVE_API_KEY'], $_ENV['LALAMOVE_SECRET']);

// Prepare headers (including Market header)
$headers = [
    'Authorization: hmac ' . $_ENV['LALAMOVE_API_KEY'] . ':' . $timestamp . ':' . $signature,
    'Content-Type: application/json',
    'Accept: application/json',
    'Market: MY'  // Malaysia market code as header
];

echo "Making API request to: $url\n";

// Make the request using cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "Response Code: $httpCode\n";
if ($error) {
    echo "cURL Error: $error\n";
}

echo "Response Body:\n";
echo $response . "\n\n";

if ($httpCode == 200) {
    echo "✅ API test successful!\n";
    $data = json_decode($response, true);
    if (isset($data['data']['quotationId'])) {
        echo "Quotation ID: " . $data['data']['quotationId'] . "\n";
    }
    if (isset($data['data']['priceBreakdown']['total'])) {
        echo "Total Price: " . $data['data']['priceBreakdown']['total'] . " " . ($data['data']['priceBreakdown']['currency'] ?? '') . "\n";
    }
} else {
    echo "❌ API test failed!\n";
    $errorData = json_decode($response, true);
    if (isset($errorData['message'])) {
        echo "Error: " . $errorData['message'] . "\n";
    }
    if (isset($errorData['errors'])) {
        echo "Detailed errors:\n";
        foreach ($errorData['errors'] as $error) {
            echo "  - " . $error['id'] . ": " . $error['message'] . "\n";
        }
    }
}