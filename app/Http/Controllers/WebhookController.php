<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Capture both parsed JSON and raw body for debugging
        Log::info('Generic webhook received', [
            'headers' => [
                'content_type' => $request->header('Content-Type'),
                'authorization' => $request->header('Authorization'),
            ],
            'query' => $request->query(),
            'body_json' => $request->all(),
            'body_raw' => $request->getContent(),
        ]);

        $event = $request->input('event');

        switch ($event) {
            case 'user.created':
                Log::info('Webhook event: user.created');
                // TODO: add user creation handling logic here
                break;
            case 'payment.succeeded':
                Log::info('Webhook event: payment.succeeded');
                // TODO: add payment processing logic here
                break;
            default:
                Log::warning('Webhook event: unknown', ['event' => $event]);
        }

        return response()->json(['status' => 'ok']);
    }
}