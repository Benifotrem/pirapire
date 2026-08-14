<?php

use App\Http\Controllers\Api\AlertsApiController;
use App\Http\Controllers\Api\EscrowApiController;
use App\Http\Controllers\Api\EscrowWebhookController;
use App\Http\Controllers\Api\VipApiController;
use Illuminate\Support\Facades\Route;

// LNbits calls this directly, authenticated by shared secret header (not the bot token).
Route::post('/escrow/webhook', EscrowWebhookController::class)->name('api.escrow.webhook');

// Everything else under /api is exclusively for the Node.js WhatsApp bot.
Route::middleware('whatsapp-bot')->group(function () {
    Route::get('/alerts/subscribers', [AlertsApiController::class, 'subscribers']);
    Route::get('/vip/{whatsappNumber}', [VipApiController::class, 'show']);

    Route::post('/escrow/jobs', [EscrowApiController::class, 'store']);
    Route::get('/escrow/jobs/{job}', [EscrowApiController::class, 'show']);
    Route::post('/escrow/jobs/{job}/release', [EscrowApiController::class, 'release']);
    Route::post('/escrow/jobs/{job}/dispute', [EscrowApiController::class, 'dispute']);
    Route::post('/escrow/jobs/{job}/cancel', [EscrowApiController::class, 'cancel']);
});
