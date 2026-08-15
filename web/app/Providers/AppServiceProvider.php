<?php

namespace App\Providers;

use App\Services\P2P\Drivers\MostroDriver;
use App\Services\P2P\Drivers\RoboSatsDriver;
use App\Services\P2P\P2POfferAggregator;
use App\View\Composers\LedDisplayComposer;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Order here is the polling order in App\Console\Commands\PollP2POffers
        // — cosmetic only, both sources are merged regardless.
        $this->app->singleton(P2POfferAggregator::class, fn ($app) => new P2POfferAggregator([
            $app->make(RoboSatsDriver::class),
            $app->make(MostroDriver::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', LedDisplayComposer::class);
    }
}
