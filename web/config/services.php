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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | LNbits (Lightning Network Job Escrow)
    |--------------------------------------------------------------------------
    |
    | Talks to a self-hosted LNbits instance's core payments API to fund
    | and pay out escrow jobs. See app/Services/Lightning/LnbitsClient.php
    | and app/Services/Escrow/EscrowService.php.
    |
    */
    'lnbits' => [
        'base_url' => env('LNBITS_BASE_URL', 'http://lnbits:5000'),
        'admin_key' => env('LNBITS_ADMIN_KEY'),
        'invoice_read_key' => env('LNBITS_INVOICE_READ_KEY'),
        'webhook_secret' => env('LNBITS_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp bot (internal service-to-service auth)
    |--------------------------------------------------------------------------
    |
    | The Node.js WhatsApp bot authenticates to routes/api.php with this
    | bearer token. Rotate it independently of any user-facing credential.
    |
    */
    'whatsapp_bot' => [
        'api_token' => env('WHATSAPP_BOT_API_TOKEN'),
    ],

    'escrow' => [
        'fee_percent' => env('ESCROW_FEE_PERCENT', 1.5),
    ],

];
