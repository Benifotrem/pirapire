<?php

namespace Tests\Feature;

use App\Filament\Widgets\LnbitsWalletWidget;
use App\Filament\Widgets\PlatformStatsWidget;
use App\Models\EscrowJob;
use App\Models\User;
use App\Services\Lightning\LnbitsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_stats_widget_totals_completed_escrow_fees(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        EscrowJob::factory()->create(['status' => 'completed', 'fee_sats' => 100, 'amount_sats' => 5000]);
        EscrowJob::factory()->create(['status' => 'completed', 'fee_sats' => 200, 'amount_sats' => 8000]);
        EscrowJob::factory()->create(['status' => 'created', 'fee_sats' => 999, 'amount_sats' => 1]);

        $this->actingAs($admin, 'web');

        Livewire::test(PlatformStatsWidget::class)
            ->assertSee('300')
            ->assertSee('13,000 sats');
    }

    public function test_wallet_widget_is_visible_to_admin_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $support = User::factory()->create(['role' => 'support']);

        $this->actingAs($admin, 'web');
        $this->assertTrue(LnbitsWalletWidget::canView());

        $this->actingAs($support, 'web');
        $this->assertFalse(LnbitsWalletWidget::canView());
    }

    public function test_wallet_widget_shows_balance_in_sats(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'web');

        $this->mock(LnbitsClient::class, function ($mock) {
            $mock->shouldReceive('getWalletDetails')
                ->once()
                ->andReturn(['name' => 'Pirapire', 'balance' => 2500000]);
        });

        Livewire::test(LnbitsWalletWidget::class)
            ->assertSee('2,500 sats');
    }

    public function test_wallet_widget_degrades_gracefully_when_lnbits_is_unreachable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin, 'web');

        $this->mock(LnbitsClient::class, function ($mock) {
            $mock->shouldReceive('getWalletDetails')->andThrow(new \RuntimeException('unreachable'));
        });

        Livewire::test(LnbitsWalletWidget::class)
            ->assertSee('No disponible');
    }
}
