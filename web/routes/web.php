<?php

use App\Http\Controllers\Auth\LnurlAuthController;
use App\Http\Controllers\Auth\StaffLnurlAuthController;
use App\Http\Controllers\Auth\StaffWhatsappAuthController;
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

// Lightning-wallet login for the Filament admin panel (App\Models\User).
// Not gated by `guest` — an already-authenticated admin hitting this page
// is "linking" a wallet to their account rather than logging in again. See
// App\Http\Controllers\Auth\StaffLnurlAuthController for the distinction.
Route::get('/staff-login', [StaffLnurlAuthController::class, 'show'])->name('staff-login');
Route::get('/staff-lnurl-auth/callback', [StaffLnurlAuthController::class, 'callback'])->name('staff-lnurl-auth.callback');
Route::get('/staff-lnurl-auth/status/{sessionId}', [StaffLnurlAuthController::class, 'status'])->name('staff-lnurl-auth.status');
Route::post('/staff-lnurl-auth/complete', [StaffLnurlAuthController::class, 'complete'])->name('staff-lnurl-auth.complete');

// WhatsApp one-time-code login for the Filament admin panel — same
// login/link duality as the wallet flow above. See
// App\Http\Controllers\Auth\StaffWhatsappAuthController.
Route::get('/staff-login-whatsapp', [StaffWhatsappAuthController::class, 'showRequest'])->name('staff-login-whatsapp');
Route::post('/staff-whatsapp-auth/request', [StaffWhatsappAuthController::class, 'request'])
    ->middleware('throttle:5,1')
    ->name('staff-whatsapp-auth.request');
Route::get('/staff-whatsapp-auth/verify', [StaffWhatsappAuthController::class, 'showVerify'])->name('staff-whatsapp-auth.verify-form');
Route::post('/staff-whatsapp-auth/verify', [StaffWhatsappAuthController::class, 'verify'])
    ->middleware('throttle:10,1')
    ->name('staff-whatsapp-auth.verify');
