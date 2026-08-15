<?php

use App\Http\Controllers\Api\EscrowWebhookController;
use App\Http\Controllers\MiniApp\AdminController as MiniAppAdminController;
use App\Http\Controllers\MiniApp\CustomerController as MiniAppCustomerController;
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

// Telegram Mini Apps — opened from a "Web App" button inside each bot.
// Authenticated per-request by AuthenticateCustomerMiniApp/AuthenticateAdminMiniApp,
// which validate Telegram's signed `initData` (sent as X-Telegram-Init-Data)
// against that Mini App's own bot token, not by a Laravel session/cookie —
// there is none, the page runs inside Telegram's webview.
Route::middleware('miniapp.customer')->prefix('miniapp/customer')->name('api.miniapp.customer.')->group(function () {
    Route::get('/me', [MiniAppCustomerController::class, 'me'])->name('me');
    Route::get('/mempool', [MiniAppCustomerController::class, 'mempool'])->name('mempool');

    Route::get('/alerts', [MiniAppCustomerController::class, 'alerts'])->name('alerts.index');
    Route::post('/alerts', [MiniAppCustomerController::class, 'storeAlert'])->name('alerts.store');
    Route::patch('/alerts/{alert}/toggle', [MiniAppCustomerController::class, 'toggleAlert'])->name('alerts.toggle');
    Route::delete('/alerts/{alert}', [MiniAppCustomerController::class, 'destroyAlert'])->name('alerts.destroy');

    Route::get('/escrow-jobs', [MiniAppCustomerController::class, 'escrowJobs'])->name('escrow.index');
    Route::post('/escrow-jobs', [MiniAppCustomerController::class, 'storeEscrowJob'])->name('escrow.store');
    Route::get('/escrow-jobs/{job}', [MiniAppCustomerController::class, 'showEscrowJob'])->name('escrow.show');
    Route::post('/escrow-jobs/{job}/release', [MiniAppCustomerController::class, 'releaseEscrowJob'])->name('escrow.release');
    Route::post('/escrow-jobs/{job}/dispute', [MiniAppCustomerController::class, 'disputeEscrowJob'])->name('escrow.dispute');
});

Route::middleware('miniapp.admin')->prefix('miniapp/admin')->name('api.miniapp.admin.')->group(function () {
    Route::get('/me', [MiniAppAdminController::class, 'me'])->name('me');
    Route::get('/stats', [MiniAppAdminController::class, 'stats'])->name('stats');
    Route::get('/wallet', [MiniAppAdminController::class, 'wallet'])->name('wallet');

    Route::get('/escrow-jobs', [MiniAppAdminController::class, 'escrowJobs'])->name('escrow.index');
    Route::get('/escrow-jobs/{job}', [MiniAppAdminController::class, 'showEscrowJob'])->name('escrow.show');

    Route::get('/disputes', [MiniAppAdminController::class, 'disputes'])->name('disputes.index');
    Route::get('/disputes/{dispute}', [MiniAppAdminController::class, 'showDispute'])->name('disputes.show');
    Route::post('/disputes/{dispute}/resolve', [MiniAppAdminController::class, 'resolveDispute'])->name('disputes.resolve');
});
