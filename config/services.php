<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],



    'saanapay' => [
        'collection' => [
            'base_url' => env('SAANAPAY_COLLECTION_BASE_URL'),
            'api_token' => env('SAANAPAY_COLLECTION_API_TOKEN'),
            'account_name_prefix' => env('SAANAPAY_ACCOUNT_NAME_PREFIX'),
            'request_expires_in_minutes' => env('SAANAPAY_REQUEST_EXPIRES_IN_MINUTE', 60),
        ],
        'payout' => [
            'base_url' => env('SAANAPAY_PAYOUT_BASE_URL'),
            'api_token' => env('SAANAPAY_PAYOUT_API_TOKEN'),
        ],
    ],

];
