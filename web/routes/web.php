<?php

use App\Http\Controllers\Auth\LnurlAuthController;
use App\Http\Controllers\Auth\StaffLnurlAuthController;
use App\Http\Controllers\Auth\StaffTelegramAuthController;
use App\Http\Controllers\Auth\TelegramLinkController;
use App\Http\Controllers\CustomerTelegramLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EscrowDashboardController;
use App\Http\Controllers\LedAdSubmissionController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Help center: step-by-step usage manual + FAQ, bilingual like the rest of
// the public site — see lang/{es,en}/faq.php and resources/views/faq.blade.php.
Route::get('/faq', fn () => view('faq'))->name('faq');

// Public-site language switch (English/Spanish) — see App\Http\Middleware\SetLocale.
Route::get('/lang/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

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

    // Links this Customer row (identified by linking_key from LNURL-auth) to
    // a Telegram chat — see App\Http\Controllers\CustomerTelegramLinkController
    // and TelegramCustomerWebhookController's "/vincular CODE" handling.
    Route::get('/dashboard/link-telegram', [CustomerTelegramLinkController::class, 'show'])->name('customer-link-telegram');
    Route::get('/dashboard/link-telegram/status/{code}', [CustomerTelegramLinkController::class, 'status'])->name('customer-telegram-link.status');

    // The job board — same App\Services\Escrow\EscrowService and escrow_jobs
    // rows behind the Telegram bot's /escrow commands and the Mini App
    // (App\Http\Controllers\MiniApp\CustomerController); this is a third,
    // plain-browser front end onto the same one job board, not a separate one.
    Route::get('/dashboard/escrow', [EscrowDashboardController::class, 'board'])->name('escrow.board');
    Route::post('/dashboard/escrow/jobs', [EscrowDashboardController::class, 'store'])->name('escrow.store');
    Route::post('/dashboard/escrow/jobs/{job}/cancel', [EscrowDashboardController::class, 'cancel'])->name('escrow.cancel');
    Route::post('/dashboard/escrow/jobs/{job}/apply', [EscrowDashboardController::class, 'apply'])->name('escrow.apply');
    Route::post('/dashboard/escrow/jobs/{job}/applications/{application}/accept', [EscrowDashboardController::class, 'accept'])->name('escrow.accept');
    Route::post('/dashboard/escrow/jobs/{job}/deliver', [EscrowDashboardController::class, 'deliver'])->name('escrow.deliver');
    Route::get('/dashboard/escrow/jobs/{job}/proof', [EscrowDashboardController::class, 'proof'])->name('escrow.proof');
    Route::post('/dashboard/escrow/jobs/{job}/release', [EscrowDashboardController::class, 'release'])->name('escrow.release');
    Route::post('/dashboard/escrow/jobs/{job}/dispute', [EscrowDashboardController::class, 'dispute'])->name('escrow.dispute');
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
