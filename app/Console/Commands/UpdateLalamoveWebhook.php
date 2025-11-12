<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LalamoveService;

class UpdateLalamoveWebhook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lalamove:webhook {url? : Webhook URL to register. Defaults to config(lalamove.webhook_url)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register or update the Lalamove webhook URL';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $url = $this->argument('url') ?: config('lalamove.webhook_url');
        if (!$url) {
            $this->error('Webhook URL is not provided. Pass it as an argument or set LALAMOVE_WEBHOOK_URL in .env');
            return 1;
        }

        /** @var LalamoveService $service */
        $service = app(LalamoveService::class);
        $this->info('Updating Lalamove webhook to: ' . $url);
        $result = $service->updateWebhook($url);

        if (($result['success'] ?? false) === true) {
            $this->info('✅ Webhook updated successfully.');
            if (!empty($result['data'])) {
                $this->line(json_encode($result['data']));
            }
            return 0;
        }

        $status = $result['status'] ?? 'unknown';
        $this->error('❌ Failed to update webhook. HTTP status: ' . $status);
        if (!empty($result['error'])) {
            $this->line(json_encode($result['error']));
        }
        return 1;
    }
}