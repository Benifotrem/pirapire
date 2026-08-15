<?php

use App\Http\Controllers\Auth\LnurlAuthController;
use App\Http\Controllers\Auth\StaffLnurlAuthController;
use App\Http\Controllers\Auth\StaffTelegramAuthController;
use App\Http\Controllers\Auth\TelegramLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LedAdSubmissionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Self-service form for the header LED ticker's placeholder ad — see
// App\Http\Controllers\LedAdSubmissionController. Submissions land as
// `pending` and only reach the public ticker once approved from Filament
// (App\Filament\Resources\LedAdSubmissionResource).
Route::get('/anunciar', [LedAdSubmissionController::class, 'show'])->name('led-ad-submission.show');
Route::post('/anunciar', [LedAdSubmissionController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('led-ad-submission.store');

// Telegram Mini Apps — static shells opened from a "Web App" button inside
// each bot. No server-side auth here (there's no Laravel session inside
// Telegram's webview); the page authenticates its own API calls with
// Telegram's signed initData once Telegram.WebApp is ready client-side —
// see routes/api.php's miniapp.customer/miniapp.admin groups.
Route::get('/miniapp/customer', fn () => view('miniapp.customer'))->name('miniapp.customer');
Route::get('/miniapp/admin', fn () => view('miniapp.admin'))->name('miniapp.admin');

// LNURL-auth passwordless login (LUD-04). See app/Services/Lnurl/LnurlAuthService.php.
Route::middleware('guest:customer')->group(function () {
    Route::get('/login', [LnurlAuthController::class, 'show'])->name('login');
    Route::get('/lnurl-auth/status/{sessionId}', [LnurlAuthController::class, 'status'])
        ->name('lnurl-auth.status');
    Route::post('/lnurl-auth/complete', [LnurlAuthController::class, 'complete'])
        ->name('lnurl-auth.complete');
});

// Wallet-facing callback — not behind the `guest` middleware since it's
// called by the customer's Lightning wallet app, not a logged-in browser.
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

// Telegram login for the Filament admin panel. Linking (learning the admin's
// chat_id) requires an authenticated session because Telegram bots can't
// message a chat first — see App\Http\Controllers\Auth\TelegramLinkController
// and App\Http\Controllers\TelegramWebhookController. Login itself
// (StaffTelegramAuthController) only needs the linked chat_id, already on
// the User row, so it stays a guest route like the other two.
Route::middleware('auth:web')->group(function () {
    Route::get('/staff-link-telegram', [TelegramLinkController::class, 'show'])->name('staff-link-telegram');
    Route::get('/staff-link-telegram/status/{code}', [TelegramLinkController::class, 'status'])->name('staff-telegram-link.status');
});

Route::get('/staff-login-telegram', [StaffTelegramAuthController::class, 'showRequest'])->name('staff-login-telegram');
Route::post('/staff-telegram-auth/request', [StaffTelegramAuthController::class, 'request'])
    ->middleware('throttle:5,1')
    ->name('staff-telegram-auth.request');
Route::get('/staff-telegram-auth/verify', [StaffTelegramAuthController::class, 'showVerify'])->name('staff-telegram-auth.verify-form');
Route::post('/staff-telegram-auth/verify', [StaffTelegramAuthController::class, 'verify'])
    ->middleware('throttle:10,1')
    ->name('staff-telegram-auth.verify');
