<?php


return [
    'configs' => [
        [
            'name' => 'saanapay',
            'signing_secret' => env('WEBHOOK_CLIENT_SECRET', ''),
            'signature_header_name' => 'no-header',
            'signature_validator' => App\Helpers\SaanaPayWebhookSignatureValidator::class,
            'webhook_profile' => \Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile::class,
            'webhook_model' => \Spatie\WebhookClient\Models\WebhookCall::class,
            'process_webhook_job' => \App\Jobs\SaanaPayWebhookInterceptJob::class,
        ],
    ],
];
