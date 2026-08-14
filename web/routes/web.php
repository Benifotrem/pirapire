<?php

use App\Http\Controllers\Auth\LnurlAuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// LNURL-auth passwordless login (LUD-04). See app/Services/Lnurl/LnurlAuthService.php.
Route::middleware('guest:customer')->group(function () {
    Route::get('/login', [LnurlAuthController::class, 'show'])->name('login');
    Route::get('/lnurl-auth/status/{sessionId}', [LnurlAuthController::class, 'status'])
        ->name('lnurl-auth.status');
    Route::post('/lnurl-auth/complete', [LnurlAuthController::class, 'complete'])
        ->name('lnurl-auth.complete');
});

// Wallet-facing callback — not behind the `guest` middleware since it's
// called by the WhatsApp/Lightning wallet app, not a logged-in browser.
Route::get('/lnurl-auth/callback', [LnurlAuthController::class, 'callback'])->name('lnurl-auth.callback');

Route::middleware('auth:customer')->group(function () {
    Route::post('/logout', [LnurlAuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/alerts', [DashboardController::class, 'storeAlert'])->name('alerts.store');
    Route::patch('/dashboard/alerts/{alert}/toggle', [DashboardController::class, 'toggleAlert'])->name('alerts.toggle');
    Route::delete('/dashboard/alerts/{alert}', [DashboardController::class, 'destroyAlert'])->name('alerts.destroy');
});
