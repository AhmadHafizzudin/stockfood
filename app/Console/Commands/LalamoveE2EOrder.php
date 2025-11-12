<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\LalamoveService;

class LalamoveE2EOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lalamove:e2e-order {order_id? : Order ID or "latest"} {--serviceType=MOTORCYCLE}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run an end-to-end Lalamove test: quotation → details → create order';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Lalamove E2E order test');

        try {
            $service = new LalamoveService();

            $orderId = $this->argument('order_id');
            $serviceType = $this->option('serviceType') ?? 'MOTORCYCLE';

            // Support special keyword to auto-select latest eligible order
            if ($orderId === 'latest') {
                $this->info('Selecting latest order with usable restaurant and customer locations...');
                $order = \App\Models\Order::with('restaurant','customer')
                    ->whereNotNull('delivery_address')
                    ->orderByDesc('id')
                    ->first();
                if ($order) {
                    $orderId = $order->id;
                    $this->info("Loaded latest order #{$orderId}");
                }
            } else if ($orderId) {
                $this->info("Loading order #{$orderId} for dynamic pickup/dropoff...");
                $order = \App\Models\Order::with('restaurant','customer')->find($orderId);
            }

            if (isset($orderId)) {
                if (!$order || !$order->restaurant) {
                    $this->error('Order or restaurant not found. Falling back to static test addresses.');
                } else {
                    // Build pickup from restaurant location
                    $pickup = $service->formatStop(
                        $order->restaurant->latitude ?? 0,
                        $order->restaurant->longitude ?? 0,
                        $order->restaurant->address ?? 'Restaurant address not set'
                    );

                    // Build dropoff from customer's delivery address JSON
                    $delivery = [];
                    if (!empty($order->delivery_address)) {
                        $delivery = json_decode($order->delivery_address, true) ?: [];
                    }
                    $dropoff = $service->formatStop(
                        $delivery['latitude'] ?? 0,
                        $delivery['longitude'] ?? 0,
                        $delivery['address'] ?? 'Customer address not set'
                    );

                    $quotationPayload = $service->formatQuotationData($serviceType, [$pickup, $dropoff], 'en_MY');
                }
            }

            // Fallback to static Puchong test route if no order provided or order invalid
            if (!isset($quotationPayload)) {
                $pickup = $service->formatStop(
                    3.0136,
                    101.6168,
                    'IOI Mall Puchong, Bandar Puchong Jaya, 47100 Puchong, Selangor, Malaysia'
                );
                $dropoff = $service->formatStop(
                    3.0109,
                    101.6179,
                    'Bandar Puteri Puchong, 47100 Puchong, Selangor, Malaysia'
                );
                $quotationPayload = $service->formatQuotationData($serviceType, [$pickup, $dropoff], 'en_MY');
            }

            $this->info('Requesting quotation...');
            $quote = $service->getQuotation($quotationPayload);
            if (!$quote['success']) {
                $this->error('Quotation failed');
                $this->line(json_encode($quote, JSON_PRETTY_PRINT));
                return 1;
            }

            $quotationId = $quote['data']['data']['quotationId'] ?? null;
            $pb = $quote['data']['data']['priceBreakdown'] ?? [];
            $currency = $pb['currency'] ?? '';
            $priorityFee = isset($pb['priorityFee']) ? (float)$pb['priorityFee'] : 0.0;
            $totalExcludePriorityFee = isset($pb['totalExcludePriorityFee']) ? (float)$pb['totalExcludePriorityFee'] : null;
            $total = isset($pb['total']) ? (float)$pb['total'] : null;
            $effectiveTotal = $totalExcludePriorityFee ?? ($total !== null ? ($total - $priorityFee) : null);
            $this->info("Quotation OK: ID={$quotationId}, totalExclPriority={$effectiveTotal} {$currency} (raw total={$total}, priorityFee={$priorityFee})");

            if (!$quotationId) {
                $this->error('Quotation ID missing; cannot proceed to order creation.');
                return 1;
            }

            $this->info('Fetching quotation details to obtain stopIds...');
            $details = $service->getQuotationDetails($quotationId);
            if (!$details['success']) {
                $this->error('Failed to get quotation details');
                $this->line(json_encode($details, JSON_PRETTY_PRINT));
                return 1;
            }

            $stops = $details['data']['data']['stops'] ?? [];
            if (count($stops) < 2 || !isset($stops[0]['stopId']) || !isset($stops[1]['stopId'])) {
                $this->error('Stop IDs missing in quotation details');
                $this->line(json_encode($details, JSON_PRETTY_PRINT));
                return 1;
            }

            $senderStopId = $stops[0]['stopId'];
            $recipientStopId = $stops[1]['stopId'];

            $this->info('Creating order using quotationId and stopIds...');
            // Normalize MY phone numbers to E.164 where possible
            $normalizePhone = function($phone) {
                $p = preg_replace('/[^0-9]/', '', (string)$phone);
                if (!$p) return null;
                // Handle leading 0 local format
                if (strpos($p, '60') === 0) {
                    // already country-coded
                    return '+' . $p;
                }
                if ($p[0] === '0') {
                    return '+60' . substr($p, 1);
                }
                // If already looks like E.164 without plus
                if (strlen($p) >= 9) {
                    return '+' . $p;
                }
                return null;
            };

            $senderPhone = isset($order) && $order->restaurant && !empty($order->restaurant->phone) ? $order->restaurant->phone : null;
            $recipientPhone = isset($delivery['contact_person_number']) && !empty($delivery['contact_person_number']) ? $delivery['contact_person_number'] : null;
            $senderPhone = $normalizePhone($senderPhone) ?: '+60111111111';
            $recipientPhone = $normalizePhone($recipientPhone) ?: '+60122222222';

            $orderPayload = [
                'data' => [
                    'quotationId' => $quotationId,
                    'sender' => [
                        'stopId' => $senderStopId,
                        'name' => isset($order) && $order->restaurant ? ($order->restaurant->name ?? 'Restaurant') : 'Test Sender',
                        // Use normalized restaurant phone if available, else valid E.164 placeholder
                        'phone' => $senderPhone
                    ],
                    'recipients' => [
                        [
                            'stopId' => $recipientStopId,
                            'name' => isset($order) && $order->customer ? ($order->customer->name ?? 'Customer') : 'Test Recipient',
                            // Use normalized delivery phone if available, else valid E.164 placeholder
                            'phone' => $recipientPhone
                        ]
                    ]
                ]
            ];

            $orderResp = $service->createOrder($orderPayload);
            if (!$orderResp['success']) {
                $this->error('Create order failed');
                $this->line(json_encode($orderResp, JSON_PRETTY_PRINT));
                return 1;
            }

            $orderId = $orderResp['data']['data']['orderId'] ?? null;
            $this->info('Order created successfully');
            $this->line('Order ID: ' . ($orderId ?? 'N/A'));
            $this->line(json_encode($orderResp['data'], JSON_PRETTY_PRINT));

            $this->info('E2E test complete. Watch the webhook URL for callbacks.');
            return 0;
        } catch (\Exception $e) {
            Log::error('Lalamove E2E order command error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->error('Unexpected error: ' . $e->getMessage());
            return 1;
        }
    }
}