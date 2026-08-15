<?php

use App\Http\Controllers\Api\EscrowWebhookController;
use App\Http\Controllers\TelegramCustomerWebhookController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

// LNbits calls this directly, authenticated by shared secret header (not the bot token).
Route::post('/escrow/webhook', EscrowWebhookController::class)->name('api.escrow.webhook');

// Private admin ops bot — used only to learn an admin's chat_id during the
// Telegram-link handshake (see App\Http\Controllers\Auth\TelegramLinkController).
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])->name('api.telegram.webhook');

// Public customer-facing bot — /mempool, /vip, /escrow, and the entry point
// for RoboSats alert subscriptions. Deliberately a separate bot/webhook
// from the one above. Both are authenticated by Telegram's own
// X-Telegram-Bot-Api-Secret-Token header, not a bearer token — there's no
// longer a separate bot process calling into Laravel (see README "Panel de
// administración" / "Alertas P2P de RoboSats" for the history of why).
Route::post('/telegram/customer-webhook', [TelegramCustomerWebhookController::class, 'handle'])
    ->name('api.telegram.customer-webhook');
