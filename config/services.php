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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'phonepe' => [
        'merchant_id' => env('PHONEPE_MERCHANT_ID', 'PGTESTPAYUAT'),
        'salt_key' => env('PHONEPE_API_KEY', '099eb0cd-02cf-4e2a-8aca-3e6c6aff0399'),
        'salt_index' => env('PHONEPE_SALT_INDEX', '1'),
        'env' => env('PHONEPE_ENV', 'sandbox'), // sandbox or production
        'base_url' => env('PHONEPE_ENV', 'sandbox') === 'production' 
            ? 'https://api.phonepe.com/apis/hermes/pg/v1/pay' 
            : 'https://api-preprod.phonepe.com/apis/pg-sandbox/pg/v1/pay',
    ],

];
