<?php

namespace App\Filament\Widgets;

use App\Services\Lightning\LnbitsClient;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Shows the platform's LNbits wallet balance on the admin dashboard. The
 * admin key never leaves the server — this widget only ever uses
 * LnbitsClient::getWalletDetails(), which authenticates with the
 * lower-privilege invoice/read key (see LnbitsClient). Restricted to the
 * 'admin' role (not 'support'): a live balance is sensitive operational
 * data, unlike the read-only aggregate stats in PlatformStatsWidget.
 */
class LnbitsWalletWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return Auth::guard('web')->user()?->role === 'admin';
    }

    protected function getStats(): array
    {
        // Cached briefly so the dashboard doesn't hit LNbits on every
        // request/poll — a live node call is expensive and not that
        // time-sensitive for a balance display.
        $wallet = Cache::remember('admin-dashboard:lnbits-wallet', 30, function () {
            try {
                return app(LnbitsClient::class)->getWalletDetails();
            } catch (Throwable) {
                return null;
            }
        });

        if ($wallet === null) {
            return [
                Stat::make('Wallet LNbits', 'No disponible')
                    ->description('No se pudo contactar a LNbits')
                    ->color('danger'),
            ];
        }

        $balanceSats = (int) round(($wallet['balance'] ?? 0) / 1000);

        return [
            Stat::make('Wallet LNbits', number_format($balanceSats).' sats')
                ->description($wallet['name'] ?? 'Pirapire')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('warning'),
        ];
    }
}
