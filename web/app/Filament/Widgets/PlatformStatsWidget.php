<?php

namespace App\Filament\Widgets;

use App\Services\Stats\PlatformStatsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Business metrics for the admin dashboard. Every number here is derived
 * from the platform's own database (no external calls, unlike
 * LnbitsWalletWidget), so it's visible to both 'admin' and 'support' roles.
 */
class PlatformStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $stats = app(PlatformStatsService::class)->compute();

        return [
            Stat::make('Sats cobrados en comisión', number_format($stats['fee_sats']))
                ->description('Fee acumulado de escrows completados')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Volumen de escrow', number_format($stats['volume_sats']).' sats')
                ->description('Total pagado a freelancers/clientes')
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->color('primary'),

            Stat::make('Escrows activos', $stats['active_jobs'])
                ->description('Creados, fondeados, en curso o en disputa')
                ->descriptionIcon('heroicon-m-clock')
                ->color($stats['active_jobs'] > 0 ? 'warning' : 'gray'),

            Stat::make('Disputas abiertas', $stats['open_disputes'])
                ->description('Requieren resolución manual')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($stats['open_disputes'] > 0 ? 'danger' : 'gray'),

            Stat::make('VIPs activos', $stats['active_vips'])
                ->description('Suscripciones vigentes')
                ->descriptionIcon('heroicon-m-star')
                ->color('warning'),

            Stat::make('Clientes registrados', number_format($stats['customers']))
                ->description('Cuentas creadas vía LNURL-auth')
                ->descriptionIcon('heroicon-m-users')
                ->color('gray'),
        ];
    }
}
