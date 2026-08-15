<?php

namespace App\Services\P2P;

use App\Contracts\P2PProviderInterface;
use App\DTOs\NormalizedP2POffer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Polls every registered App\Contracts\P2PProviderInterface driver for a
 * currency and merges the results. Each driver is polled independently —
 * if one throws (a dead relay, Tor down, a malformed response), that
 * source is skipped for this cycle and logged, but the others still run
 * normally. App\Console\Commands\PollP2POffers is the only caller.
 */
class P2POfferAggregator
{
    /** @param  P2PProviderInterface[]  $drivers */
    public function __construct(private readonly array $drivers) {}

    /** Source names this aggregator can serve — matches the `alerts.source` column's allowed values, minus "all". */
    public function availableSources(): array
    {
        return array_map(fn (P2PProviderInterface $driver) => $driver->getProviderName(), $this->drivers);
    }

    /**
     * @param  string[]  $sources  driver names to include, or ['all'] for every registered driver
     * @return NormalizedP2POffer[]
     */
    public function collect(string $currency, array $sources = ['all']): array
    {
        $offers = [];

        foreach ($this->drivers as $driver) {
            if (! in_array('all', $sources, true) && ! in_array($driver->getProviderName(), $sources, true)) {
                continue;
            }

            try {
                $offers = [...$offers, ...$driver->fetchOffers($currency)];
            } catch (Throwable $e) {
                Log::error('P2P provider failed, skipping it for this poll', [
                    'provider' => $driver->getProviderName(),
                    'currency' => $currency,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $offers;
    }
}
