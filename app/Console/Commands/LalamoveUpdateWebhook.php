<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LalamoveService;

class LalamoveUpdateWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lalamove:webhook {url? : Optional webhook URL; defaults to config(lalamove.webhook_url)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Lalamove webhook URL (account-level) via API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $url = $this->argument('url') ?: config('lalamove.webhook_url');

        if (empty($url)) {
            $this->error('Webhook URL not provided. Pass it as an argument or set LALAMOVE_WEBHOOK_URL in .env.');
            return 1;
        }

        $this->info('Updating Lalamove webhook URL...');
        $this->line('Using URL: ' . $url);

        $service = new LalamoveService();
        $result = $service->updateWebhook($url);

        if (($result['success'] ?? false) === true) {
            $this->info('✅ Webhook updated successfully.');
            $this->table(['Key', 'Value'], [
                ['status', 'success'],
                ['configured_url', $url],
            ]);
            return 0;
        }

        $status = $result['status'] ?? 'unknown';
        $error = is_array($result['error'] ?? null) ? json_encode($result['error']) : ($result['error'] ?? 'unknown error');
        $this->error("❌ Webhook update failed (status: {$status}). Error: {$error}");
        return 1;
    }
}