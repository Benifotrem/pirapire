<?php

namespace App\Services\RoboSats;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches the public RoboSats order book. No default base URL — see the
 * "robosats" section of config/services.php for why.
 */
class RoboSatsClient
{
    private const CURRENCY_CODES = ['PYG' => 600, 'USD' => 840];

    private readonly ?string $baseUrl;

    private readonly ?string $proxyUrl;

    public function __construct(?string $baseUrl = null, ?string $proxyUrl = null)
    {
        $this->baseUrl = $baseUrl ?? config('services.robosats.api_base_url');
        $this->proxyUrl = $proxyUrl ?? config('services.robosats.proxy_url');
    }

    public function isConfigured(): bool
    {
        return (bool) $this->baseUrl;
    }

    /** @return array<int, array<string, mixed>> */
    public function fetchBook(string $currency = 'PYG'): array
    {
        if (! $this->baseUrl) {
            return [];
        }

        $isoCode = self::CURRENCY_CODES[$currency] ?? null;
        if (! $isoCode) {
            return [];
        }

        try {
            $response = Http::timeout(10)
                ->when($this->proxyUrl, fn ($http) => $http->withOptions(['proxy' => $this->proxyUrl]))
                ->get(rtrim($this->baseUrl, '/')."/book/?currency={$isoCode}");

            if ($response->failed()) {
                Log::warning('RoboSats book request returned non-200', ['status' => $response->status()]);

                return [];
            }

            $data = $response->json();

            return is_array($data) && array_is_list($data) ? $data : [];
        } catch (Throwable $e) {
            // Covers a Tor/proxy outage the same as any other connectivity
            // failure: robosats:poll (App\Console\Commands\PollRoboSatsOrders)
            // treats an empty book as "nothing new" and moves on, so this
            // never interrupts the scheduler or unrelated commands
            // (/mempool, /escrow) — see that command's class docblock.
            Log::error('Failed to fetch RoboSats order book', ['err' => $e->getMessage()]);

            return [];
        }
    }
}
