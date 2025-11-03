<?php

namespace App\Http\Controllers;

use App\Services\LalamoveService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LalamoveController extends Controller
{
    protected $lalamoveService;

    public function __construct(LalamoveService $lalamoveService)
    {
        $this->lalamoveService = $lalamoveService;
    }

    /**
     * Get delivery quotation
     */
    public function getQuotation(Request $request): JsonResponse
    {
        $request->validate([
            'service_type' => 'required|string',
            'pickup.lat' => 'required|numeric',
            'pickup.lng' => 'required|numeric',
            'pickup.address' => 'required|string',
            'dropoffs' => 'required|array|min:1',
            'dropoffs.*.lat' => 'required|numeric',
            'dropoffs.*.lng' => 'required|numeric',
            'dropoffs.*.address' => 'required|string',
        ]);

        // Format stops array
        $stops = [];
        
        // Add pickup stop
        $stops[] = $this->lalamoveService->formatStop(
            $request->input('pickup.lat'),
            $request->input('pickup.lng'),
            $request->input('pickup.address'),
            $request->input('pickup.contact_name'),
            $request->input('pickup.contact_phone')
        );

        // Add dropoff stops
        foreach ($request->input('dropoffs') as $dropoff) {
            $stops[] = $this->lalamoveService->formatStop(
                $dropoff['lat'],
                $dropoff['lng'],
                $dropoff['address'],
                $dropoff['contact_name'] ?? null,
                $dropoff['contact_phone'] ?? null
            );
        }

        // Format quotation data
        $quotationData = $this->lalamoveService->formatQuotationData(
            $request->input('service_type', 'MOTORCYCLE'),
            $stops,
            $request->input('language', 'en_MY')
        );

        $result = $this->lalamoveService->getQuotation($quotationData);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Quotation retrieved successfully',
                'data' => $result['data']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to get quotation',
            'error' => $result['error']
        ], $result['status'] ?? 500);
    }

    /**
     * Test quotation with sample data (based on your example)
     */
    public function testQuotation(): JsonResponse
    {
        try {
            // Using correct data format (market sent as header)
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

            $result = $this->lalamoveService->getQuotation($quotationData);

            if ($result['success']) {
                $data = $result['data']['data'] ?? $result['data'];
                
                return response()->json([
                    'success' => true,
                    'message' => 'Test quotation successful',
                    'quotation_id' => $data['quotationId'] ?? null,
                    'total' => $data['priceBreakdown']['total'] ?? null,
                    'currency' => $data['priceBreakdown']['currency'] ?? null,
                    'stops' => $data['stops'] ?? [],
                    'full_response' => $result['data']
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Test quotation failed',
                'error' => $result['error'],
                'debug_info' => [
                    'api_key' => config('lalamove.api_key') ? 'Set' : 'Not Set',
                    'secret' => config('lalamove.secret') ? 'Set' : 'Not Set',
                    'base_url' => config('lalamove.base_url'),
                    'version' => config('lalamove.version')
                ]
            ], $result['status'] ?? 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Test quotation exception',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Create order from quotation
     */
    public function createOrder(Request $request): JsonResponse
    {
        $request->validate([
            'quotation_id' => 'required|string',
        ]);

        $orderData = [
            'data' => [
                'quotationId' => $request->input('quotation_id'),
                // Add additional order data if needed
            ]
        ];

        $result = $this->lalamoveService->createOrder($orderData);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $result['data']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to create order',
            'error' => $result['error']
        ], $result['status'] ?? 500);
    }

    /**
     * Get order details
     */
    public function getOrder(Request $request, $orderId): JsonResponse
    {
        $result = $this->lalamoveService->getOrder($orderId);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Order details retrieved successfully',
                'data' => $result['data']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to get order details',
            'error' => $result['error']
        ], $result['status'] ?? 500);
    }

    /**
     * Cancel order
     */
    public function cancelOrder(Request $request, $orderId): JsonResponse
    {
        $result = $this->lalamoveService->cancelOrder($orderId);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
                'data' => $result['data']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to cancel order',
            'error' => $result['error']
        ], $result['status'] ?? 500);
    }
}