<?php

namespace App\Services\Stats;

use App\Models\Customer;
use App\Models\EscrowDispute;
use App\Models\EscrowJob;
use App\Models\VipSubscription;
use Illuminate\Support\Facades\Cache;

/**
 * Business metrics shared by the Filament admin dashboard
 * (App\Filament\Widgets\PlatformStatsWidget) and the admin Mini App's
 * /api/miniapp/admin/stats endpoint, so the two surfaces can't drift.
 */
class PlatformStatsService
{
    /** @return array<string, int> */
    public function compute(): array
    {
        // Cached briefly: these are dashboard vanity metrics, not
        // operational data that needs to be second-fresh, and the escrow
        // fee/volume sums scan the whole completed-jobs table.
        return Cache::remember('admin-dashboard:platform-stats', 30, fn () => [
            'fee_sats' => (int) EscrowJob::where('status', 'completed')->sum('fee_sats'),
            'volume_sats' => (int) EscrowJob::where('status', 'completed')->sum('amount_sats'),
            'active_jobs' => EscrowJob::whereIn('status', ['open', 'assigned', 'funded', 'in_progress', 'delivered', 'disputed'])->count(),
            'open_disputes' => EscrowDispute::where('status', 'open')->count(),
            'active_vips' => VipSubscription::where('status', 'active')->where('expires_at', '>', now())->count(),
            'customers' => Customer::count(),
        ]);
    }
}
