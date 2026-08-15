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

    EscrowJob::where('status', 'created')
        ->where('expires_at', '<=', now())
        ->each(fn (EscrowJob $job) => $escrow->cancelUnfunded($job));
})->everyFiveMinutes()->name('escrow-jobs:cancel-expired');

// Replaces the old Node bot's RoboSatsPoller (node-cron on an arbitrary
// interval, default 60s) — Laravel's scheduler tops out at per-minute
// granularity, close enough to that default. No-ops with a warning if
// ROBOSATS_API_BASE_URL is unset (see App\Console\Commands\PollRoboSatsOrders).
Schedule::command('robosats:poll')->everyMinute()->name('robosats:poll')->withoutOverlapping();
