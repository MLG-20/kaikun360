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

    // SMS (B16). Fournisseur `log` par défaut (dev) ou `twilio` (prod).
    'sms' => [
        'provider' => env('SMS_PROVIDER', 'log'),
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
    ],

    // PSP PayTech (B14). Aucune valeur en dur : tout vient de l'environnement.
    // base_url : engine-sandbox.pay.tech (test) puis engine.pay.tech (prod).
    'paytech' => [
        'base_url' => env('PAYTECH_BASE_URL', 'https://engine-sandbox.pay.tech'),
        'api_key' => env('PAYTECH_API_KEY'),
        'signing_key' => env('PAYTECH_SIGNING_KEY'),
        'webhook_url' => env('PAYTECH_WEBHOOK_URL'),
    ],

];
