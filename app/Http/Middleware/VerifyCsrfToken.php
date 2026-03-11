<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        '/webhooks/flutterwave',
        '/webhooks/zeepay',
        '/webhooks/mpesacashin',
        '/kcb/ipn/receiver',
        '/webhooks/mpesa',
        '/webhooks/voomacallback',
        '/webhooks/aiprise',
        '/webhooks/intouch',
        '/webhooks/paystack',
        '/webhooks/aiprise-event',

    ];
}
