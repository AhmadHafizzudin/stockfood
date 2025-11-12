<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\LalamoveService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Models\Order;

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
                
                $pb = $data['priceBreakdown'] ?? [];
                return response()->json([
                    'success' => true,
                    'message' => 'Test quotation successful',
                    'quotation_id' => $data['quotationId'] ?? null,
                    'total' => $pb['total'] ?? null,
                    'total_exclude_priority_fee' => $pb['totalExcludePriorityFee'] ?? (($pb['total'] ?? null) !== null ? (($pb['total'] ?? 0) - ($pb['priorityFee'] ?? 0)) : null),
                    'priority_fee' => $pb['priorityFee'] ?? 0,
                    'currency' => $pb['currency'] ?? null,
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
            'sender_name' => 'sometimes|string',
            'sender_phone' => 'sometimes|string',
            'recipient_name' => 'sometimes|string',
            'recipient_phone' => 'sometimes|string',
            'remarks' => 'sometimes|string',
            'is_pod_enabled' => 'sometimes|boolean',
            'partner' => 'sometimes|string'
        ]);

        // Fetch quotation details to get stopIds like in Postman
        $quotation = $this->lalamoveService->getQuotationDetails($request->input('quotation_id'));

        if (!$quotation['success']) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get quotation details for order creation',
                'error' => $quotation['error'] ?? 'Unknown error'
            ], $quotation['status'] ?? 500);
        }

        $data = $quotation['data']['data'] ?? $quotation['data'];
        $stops = $data['stops'] ?? [];

        if (count($stops) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Quotation must have at least pickup and one dropoff stop'
            ], 422);
        }

        $senderName = $request->input('sender_name', config('app.name', 'StockFood'));
        $senderPhone = $request->input('sender_phone', '+60123456789');
        $recipientName = $request->input('recipient_name', 'Customer');
        $recipientPhone = $request->input('recipient_phone', '+60123456789');

        $orderData = [
            'data' => [
                'quotationId' => $request->input('quotation_id'),
                'sender' => [
                    'stopId' => $stops[0]['stopId'] ?? null,
                    'name' => $senderName,
                    'phone' => $senderPhone,
                ],
                'recipients' => [
                    [
                        'stopId' => $stops[1]['stopId'] ?? null,
                        'name' => $recipientName,
                        'phone' => $recipientPhone,
                        'remarks' => $request->input('remarks'),
                    ]
                ],
                'isPODEnabled' => $request->boolean('is_pod_enabled', true),
                'partner' => $request->input('partner', 'Lalamove Partner 1'),
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

    /**
     * Get quotation details (HK)
     */
    public function getQuotationDetails(Request $request, $quotationId): JsonResponse
    {
        $result = $this->lalamoveService->getQuotationDetails($quotationId);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Quotation details retrieved successfully',
                'data' => $result['data']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to get quotation details',
            'error' => $result['error']
        ], $result['status'] ?? 500);
    }

    /**
     * Stub: Add priority fee (MY)
     */
    public function addPriorityFee(Request $request, $orderId): JsonResponse
    {
        $result = $this->lalamoveService->addPriorityFee($orderId, $request->all());

        return response()->json($result, $result['success'] ? 200 : 501);
    }

    /**
     * Stub: Edit order (MY)
     */
    public function editOrder(Request $request, $orderId): JsonResponse
    {
        $result = $this->lalamoveService->editOrder($orderId, $request->all());
        return response()->json($result, $result['success'] ? 200 : 501);
    }

    /**
     * Stub: Webhook (MY)
     */
    public function updateWebhook(Request $request): JsonResponse
    {
        $url = $request->input('url') ?? config('lalamove.webhook_url');
        if (empty($url)) {
            return response()->json([
                'success' => false,
                'message' => 'Webhook URL not provided; pass request.url or set lalamove.webhook_url',
            ], 422);
        }

        $result = $this->lalamoveService->updateWebhook($url);
        return response()->json($result, ($result['success'] ?? false) ? 200 : 500);
    }

    /**
     * Webhook callback: handle Lalamove status updates and update local orders.
     * This endpoint is intended to be set as Lalamove webhook URL.
     */
    public function webhookCallback(Request $request): JsonResponse
    {
        // Log inbound webhook for traceability
        Log::info('Lalamove webhook received', [
            'headers' => [
                'authorization' => $request->header('Authorization'),
                'market' => $request->header('Market'),
                'content_type' => $request->header('Content-Type'),
                'timestamp' => $request->header('X-Request-Timestamp') ?? $request->header('X-Timestamp'),
            ],
            // Capture both parsed JSON and raw body for diagnostics (handles cases where body is not JSON)
            'body_json' => $request->all(),
            'body_raw' => $request->getContent(),
            'query' => $request->query(),
        ]);

        $payload = $request->all();
        $data = $payload['data'] ?? $payload;

        // Try to extract Lalamove orderId and status from common shapes
        $lalamoveOrderId = $data['orderId']
            ?? ($data['order']['orderId'] ?? null)
            ?? ($data['order']['id'] ?? null);

        $status = $data['status']
            ?? ($data['order']['status'] ?? null)
            ?? null;

        $eventType = $payload['event']
            ?? ($data['event'] ?? null)
            ?? ($payload['type'] ?? null);

        if (!$lalamoveOrderId) {
            Log::warning('Lalamove webhook missing orderId', ['payload' => $payload]);
            return response()->json(['success' => false, 'message' => 'Missing orderId'], 400);
        }

        $order = Order::where('lalamove_order_id', $lalamoveOrderId)->first();
        if (!$order) {
            Log::warning('Lalamove webhook order not found', ['lalamove_order_id' => $lalamoveOrderId]);
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        $newStatus = null;
        $statusNorm = is_string($status) ? strtolower($status) : '';
        $eventNorm = is_string($eventType) ? strtoupper($eventType) : '';

        // Handle documented event types first
        switch ($eventNorm) {
            case 'ORDER_STATUS_CHANGED':
                // Defer to status mapping below
                break;
            case 'DRIVER_ASSIGNED':
                // Prefer internal 'accepted' to align with queries and dashboards
                $newStatus = 'accepted';
                break;
            case 'ORDER_AMOUNT_CHANGED':
                // Update delivery charge figures if provided in payload
                $pb = $data['priceBreakdown'] ?? ($data['order']['priceBreakdown'] ?? null);
                if (is_array($pb)) {
                    $currency = $pb['currency'] ?? null;
                    $priorityFee = $pb['priorityFee'] ?? 0;
                    $total = $pb['total'] ?? null;
                    $totalExcl = $pb['totalExcludePriorityFee'] ?? null;
                    $newCharge = null;
                    if (is_numeric($totalExcl)) {
                        $newCharge = (float)$totalExcl;
                    } elseif (is_numeric($total) && is_numeric($priorityFee)) {
                        $newCharge = (float)$total - (float)$priorityFee;
                    } elseif (is_numeric($total)) {
                        $newCharge = (float)$total;
                    }
                    if ($newCharge !== null) {
                        // Update delivery charge while keeping original for reporting when possible
                        try {
                            $order->original_delivery_charge = $order->original_delivery_charge ?: $order->delivery_charge;
                        } catch (\Throwable $e) {
                            // ignore if column is missing
                        }
                        $order->delivery_charge = $newCharge;
                        $order->save();
                        Log::info('Order delivery charge updated via Lalamove webhook', [
                            'order_id' => $order->id,
                            'lalamove_order_id' => $lalamoveOrderId,
                            'currency' => $currency,
                            'new_delivery_charge' => $newCharge,
                        ]);
                    } else {
                        Log::info('ORDER_AMOUNT_CHANGED without parseable amount', ['payload' => $payload]);
                    }
                } else {
                    Log::info('ORDER_AMOUNT_CHANGED without priceBreakdown', ['payload' => $payload]);
                }
                break;
            case 'ORDER_REPLACED':
                // Replace stored Lalamove order ID with the new one
                $newOrderId = $data['newOrderId']
                    ?? ($data['order']['newOrderId'] ?? null)
                    ?? ($data['order']['orderId'] ?? null);
                if ($newOrderId) {
                    $oldOrderId = $lalamoveOrderId;
                    $order->lalamove_order_id = $newOrderId;
                    $order->save();
                    Log::info('Lalamove order ID replaced', [
                        'order_id' => $order->id,
                        'old_lalamove_order_id' => $oldOrderId,
                        'new_lalamove_order_id' => $newOrderId,
                    ]);
                } else {
                    Log::warning('ORDER_REPLACED missing newOrderId', ['payload' => $payload]);
                }
                break;
            case 'WALLET_BALANCE_CHANGED':
            case 'ORDER_EDITED':
                // Informational for now; no direct order mutation
                Log::info('Lalamove event received', [
                    'event' => $eventNorm,
                    'lalamove_order_id' => $lalamoveOrderId,
                ]);
                break;
            default:
                // If event is absent or unknown, we will attempt status mapping below
                if (!empty($eventNorm)) {
                    Log::info('Unhandled Lalamove event type', [
                        'event' => $eventNorm,
                        'lalamove_order_id' => $lalamoveOrderId,
                    ]);
                }
        }

        // If no event-driven status set, map by status string when available
        if (!$newStatus && $statusNorm) {
            switch ($statusNorm) {
                case 'accepted':
                case 'order_accepted':
                case 'driver_assigned':
                    $newStatus = 'accepted';
                    break;
                case 'assigning_driver':
                case 'ongoing':
                    $newStatus = 'out_for_delivery';
                    break;
                case 'picked_up':
                case 'order_picked_up':
                case 'driver_picked_up':
                    $newStatus = 'picked_up';
                    break;
                case 'completed':
                case 'delivered':
                    $newStatus = 'delivered';
                    break;
                case 'canceled':
                case 'cancelled':
                case 'order_cancelled':
                    $newStatus = 'canceled';
                    break;
                default:
                    Log::info('Lalamove webhook unhandled status', [
                        'lalamove_order_id' => $lalamoveOrderId,
                        'status' => $status,
                        'payload' => $payload,
                    ]);
            }
        }

        if ($newStatus) {
            // Update main status and timestamp column if exists (e.g., picked_up, delivered, canceled)
            $order->order_status = $newStatus;
            try {
                $order->{$newStatus} = now();
            } catch (\Throwable $e) {
                // Silently ignore if column doesn't exist for this status name
            }
            $order->save();
            Log::info('Order status updated via Lalamove webhook', [
                'order_id' => $order->id,
                'lalamove_order_id' => $lalamoveOrderId,
                'new_status' => $newStatus,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Webhook processed',
            'event' => $eventNorm,
            'updated_status' => $newStatus,
        ], 200);
    }

    /**
     * Mock: manually set order status for testing admin/Vendor flows.
     * Accepts either `order_id` or `lalamove_order_id`, and a `status` value.
     */
    public function mockStatus(Request $request): JsonResponse
    {
        $status = strtolower((string) $request->input('status'));
        $orderId = $request->input('order_id');
        $llOrderId = $request->input('lalamove_order_id');

        if (!$status) {
            return response()->json([
                'success' => false,
                'message' => 'Missing status. Provide one of: accepted, handover, out_for_delivery, picked_up, delivered, canceled'
            ], 422);
        }

        // Find order by provided identifier
        $order = null;
        if ($orderId) {
            $order = Order::find($orderId);
        } elseif ($llOrderId) {
            $order = Order::where('lalamove_order_id', $llOrderId)->first();
        }

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found by order_id or lalamove_order_id'
            ], 404);
        }

        // Normalize common aliases
        $aliases = [
            'accepted' => 'accepted',
            'handover' => 'handover',
            'out_for_delivery' => 'out_for_delivery',
            'picked_up' => 'picked_up',
            'delivered' => 'delivered',
            'canceled' => 'canceled',
            'cancelled' => 'canceled',
        ];
        $normalized = $aliases[$status] ?? null;
        if (!$normalized) {
            return response()->json([
                'success' => false,
                'message' => 'Unsupported status value'
            ], 422);
        }

        $order->order_status = $normalized;
        try {
            $order->{$normalized} = now();
        } catch (\Throwable $e) {
            // ignore if no column
        }
        $order->save();

        Log::info('Mock status set for order', [
            'order_id' => $order->id,
            'lalamove_order_id' => $order->lalamove_order_id,
            'new_status' => $normalized,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order status updated',
            'order_id' => $order->id,
            'lalamove_order_id' => $order->lalamove_order_id,
            'status' => $normalized,
        ]);
    }
}