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

    // SMS (B16/B18). Fournisseur `log` par défaut (dev), `twilio` ou `orange` (prod).
    'sms' => [
        'provider' => env('SMS_PROVIDER', 'log'),
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
        // Orange / Sonatel (B18.2) — identifiants via developer.orange.com.
        'orange' => [
            'client_id' => env('ORANGE_SMS_CLIENT_ID'),
            'client_secret' => env('ORANGE_SMS_CLIENT_SECRET'),
            'base_url' => env('ORANGE_SMS_BASE_URL', 'https://api.orange.com'),
            'token_url' => env('ORANGE_SMS_TOKEN_URL', 'https://api.orange.com/oauth/v3/token'),
            'sender_address' => env('ORANGE_SMS_SENDER_ADDRESS'), // ex : +221XXXXXXXXX
            'sender_name' => env('ORANGE_SMS_SENDER_NAME'),       // ex : KAIKUN360 (optionnel)
        ],
    ],

    // PSP PayTech (B14). Aucune valeur en dur : tout vient de l'environnement.
    // Aligné sur l'API réelle en F8.5 (docs.intech.sn).
    // ⚠️ Sandbox et production partagent la MÊME base : c'est `env` qui décide.
    // ⚠️ Il n'y a pas de « signing key » chez PayTech : l'API_SECRET authentifie
    //    les appels ET signe les notifications entrantes.
    'paytech' => [
        'base_url' => env('PAYTECH_BASE_URL', 'https://paytech.sn/api'),
        'api_key' => env('PAYTECH_API_KEY'),
        'api_secret' => env('PAYTECH_API_SECRET'),
        // 'test' = sandbox (PayTech ne débite alors qu'un montant aléatoire
        // entre 100 et 150 FCFA) ; 'prod' = encaissement réel.
        'env' => env('PAYTECH_ENV', 'test'),
        // URL publique de NOTRE route POST /api/v1/payments/webhook. PayTech
        // exige du HTTPS : en local, passer par un tunnel (ngrok).
        'ipn_url' => env('PAYTECH_IPN_URL'),
        // Pages du site vitrine où le client retombe après le paiement.
        'success_url' => env('PAYTECH_SUCCESS_URL'),
        'cancel_url' => env('PAYTECH_CANCEL_URL'),
    ],

    // Connexion Google (B19) : Client ID OAuth (Google Cloud Console). Sert à
    // vérifier l'audience des ID tokens reçus du frontend.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    // Webhooks sortants vers n8n (B18.1) : le backend POSTe un événement signé
    // (HMAC-SHA256) vers n8n, qui orchestre l'automatisation (WhatsApp, etc.).
    // Désactivé tant que l'URL n'est pas fournie → aucun envoi en dev/test.
    'n8n' => [
        'enabled' => env('N8N_WEBHOOK_ENABLED', false),
        'webhook_url' => env('N8N_WEBHOOK_URL'),
        'signing_secret' => env('N8N_WEBHOOK_SECRET'),
    ],

];
