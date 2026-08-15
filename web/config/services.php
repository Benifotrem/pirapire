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

    'escrow' => [
        'fee_percent' => env('ESCROW_FEE_PERCENT', 1.5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram (admin panel login) — private ops bot
    |--------------------------------------------------------------------------
    |
    | Used to deliver admin login codes directly over the Bot API. See
    | App\Services\Telegram\TelegramBotClient and
    | App\Http\Controllers\TelegramWebhookController. Distinct from the
    | public customer bot below — kept separate so a customer typing
    | "/vincular" by accident can't collide with the admin-linking flow.
    |
    */
    'telegram' => [
        'bot_token' => env('TELEGRAM_ADMIN_BOT_TOKEN'),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram (customer-facing bot)
    |--------------------------------------------------------------------------
    |
    | Public bot for /mempool, /vip, /escrow and RoboSats P2P alerts — the
    | full replacement for what used to be the WhatsApp bot. See
    | App\Http\Controllers\TelegramCustomerWebhookController and
    | App\Services\Bot\CustomerCommandRouter.
    |
    */
    'telegram_customer_bot' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'webhook_secret' => env('TELEGRAM_BOT_WEBHOOK_SECRET'),
    ],

    'mempool' => [
        'api_base_url' => env('MEMPOOL_API_BASE_URL', 'https://mempool.space/api'),
    ],

    /*
    |--------------------------------------------------------------------------
    | RoboSats (P2P order alerts)
    |--------------------------------------------------------------------------
    |
    | RoboSats is a federated, Tor-first exchange with no single stable
    | clearnet API (its own docs discourage clearnet access, and known
    | Tor2Web gateways have gone down before) — so this has no default.
    | Left unset, App\Console\Commands\PollRoboSatsOrders no-ops with a
    | logged warning; !mempool/!vip/!escrow are unaffected. Point it at a
    | coordinator you trust — self-hosted, or reached through a local Tor
    | SOCKS proxy. See README "Alertas P2P de RoboSats".
    |
    */
    'robosats' => [
        'api_base_url' => env('ROBOSATS_API_BASE_URL'),
    ],

    'alerts' => [
        'free_tier_delay_minutes' => env('FREE_TIER_DELAY_MINUTES', 10),
    ],

];
