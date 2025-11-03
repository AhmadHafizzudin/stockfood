<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LalamoveService;

class TestLalamoveConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lalamove:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Lalamove API connection and configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing Lalamove API Connection...');
        
        // Check configuration
        $this->info('Checking configuration...');
        $apiKey = config('lalamove.api_key');
        $secret = config('lalamove.secret');
        $baseUrl = config('lalamove.base_url');
        $version = config('lalamove.version');
        
        $this->table(['Config', 'Value'], [
            ['API Key', $apiKey ? 'Set (' . substr($apiKey, 0, 10) . '...)' : 'Not Set'],
            ['Secret', $secret ? 'Set (' . substr($secret, 0, 10) . '...)' : 'Not Set'],
            ['Base URL', $baseUrl],
            ['Version', $version]
        ]);
        
        if (!$apiKey || !$secret) {
            $this->error('API Key or Secret not configured. Please check your .env file.');
            return 1;
        }
        
        // Test quotation
        $this->info('Testing quotation API...');
        
        $lalamoveService = new LalamoveService();
        
        $quotationData = [
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
        ];
        
        try {
            $result = $lalamoveService->getQuotation($quotationData);
            
            if ($result['success']) {
                $this->info('✅ Quotation API test successful!');
                $data = $result['data']['data'] ?? $result['data'];
                
                if (isset($data['quotationId'])) {
                    $this->info('Quotation ID: ' . $data['quotationId']);
                }
                
                if (isset($data['priceBreakdown']['total'])) {
                    $this->info('Total Price: ' . $data['priceBreakdown']['total'] . ' ' . ($data['priceBreakdown']['currency'] ?? ''));
                }
                
                return 0;
            } else {
                $this->error('❌ Quotation API test failed!');
                $this->error('Error: ' . $result['error']);
                $this->error('Status: ' . ($result['status'] ?? 'Unknown'));
                
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('❌ Exception during API test: ' . $e->getMessage());
            return 1;
        }
    }
}