<?php

use App\Models\EscrowJob;
use App\Models\VipSubscription;
use App\Services\Escrow\EscrowService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    VipSubscription::where('status', 'active')
        ->where('expires_at', '<=', now())
        ->update(['status' => 'expired']);
})->hourly()->name('vip-subscriptions:expire');

Schedule::call(function () {
    $escrow = app(EscrowService::class);

    // 'open' postings (no freelancer picked yet, no invoice, no
    // expires_at) aren't touched here — only assignments that got a
    // funding invoice and then went stale unpaid.
    EscrowJob::where('status', 'assigned')
        ->where('expires_at', '<=', now())
        ->each(fn (EscrowJob $job) => $escrow->cancelUnfundedAssignment($job));
})->everyFiveMinutes()->name('escrow-jobs:cancel-expired');

// Replaces the old Node bot's RoboSatsPoller (node-cron on an arbitrary
// interval, default 60s) — Laravel's scheduler tops out at per-minute
// granularity, close enough to that default. Polls every configured P2P
// source (RoboSats, Mostro); no-ops with a warning if none are (see
// App\Console\Commands\PollP2POffers).
Schedule::command('p2p:poll')->everyMinute()->name('p2p:poll')->withoutOverlapping();
