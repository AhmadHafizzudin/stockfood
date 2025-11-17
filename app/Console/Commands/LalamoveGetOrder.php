<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LalamoveService;

class LalamoveGetOrder extends Command
{
    protected $signature = 'lalamove:get-order {orderId}';
    protected $description = 'Fetch Lalamove order details and print key fields';

    public function handle(): int
    {
        $orderId = $this->argument('orderId');
        $service = new LalamoveService();
        $this->info("Fetching Lalamove order: {$orderId}");
        $res = $service->getOrder($orderId);
        if (($res['success'] ?? false) !== true) {
            $this->error('Failed to fetch order');
            $this->line(json_encode($res, JSON_PRETTY_PRINT));
            return 1;
        }
        $data = $res['data']['data'] ?? $res['data'];
        $this->line(json_encode([
            'orderId' => $data['orderId'] ?? null,
            'status' => $data['status'] ?? null,
            'driverId' => $data['driverId'] ?? null,
            'priceBreakdown' => $data['priceBreakdown'] ?? null,
            'stops' => $data['stops'] ?? null,
            'shareLink' => $data['shareLink'] ?? null,
        ], JSON_PRETTY_PRINT));
        return 0;
    }
}