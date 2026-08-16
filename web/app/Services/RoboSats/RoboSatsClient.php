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
    /**
     * NOT ISO 4217 — RoboSats' `currency` query param is an index into its
     * own internal currency table (served as static JSON by the frontend at
     * /static/assets/currencies.json on any RoboSats host, coordinator or
     * not). Verified 2026-08-16 against a live coordinator's real order
     * book (Temple of Sats) — a PIX-denominated order (PIX is
     * Brazil-specific) came back tagged currency:20, which is BRL's index
     * in that table, not BRL's ISO 4217 numeric code (986). The ISO codes
     * this constant used before (PYG=600, USD=840) silently matched
     * nothing on a real coordinator — RoboSatsDriver got 200 OK with a
     * real order book back, just never anything for those two "currency"
     * values, so no alert this could produce was ever wrong, only ever
     * empty.
     */
    private const CURRENCY_INDEX = ['PYG' => 35, 'USD' => 1];

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

        $currencyIndex = self::CURRENCY_INDEX[$currency] ?? null;
        if (! $currencyIndex) {
            return [];
        }

        try {
            // type=2 asks for both BUY and SELL — App\Services\P2P\AlertMatcher
            // does the order_type filtering per-customer afterward, same as
            // it already does for amount/payment method, so there's no
            // reason to narrow this server-side.
            $response = Http::timeout(10)
                ->when($this->proxyUrl, fn ($http) => $http->withOptions(['proxy' => $this->proxyUrl]))
                ->get(rtrim($this->baseUrl, '/')."/api/book/?currency={$currencyIndex}&type=2");

            // A coordinator with zero open orders for this currency answers
            // 404 with a body like {"not_found": "No orders found, be the
            // first to make one"} — a normal empty book, not a broken
            // request. Worth a warning for any other failure status, but
            // not this one, or every low-volume currency/coordinator combo
            // would spam the log every poll.
            if ($response->status() === 404) {
                return [];
            }

            if ($response->failed()) {
                Log::warning('RoboSats book request returned non-200', ['status' => $response->status()]);

                return [];
            }

            $data = $response->json();

            return is_array($data) && array_is_list($data) ? $data : [];
        } catch (Throwable $e) {
            // Covers a Tor/proxy outage the same as any other connectivity
            // failure: App\Services\P2P\Drivers\RoboSatsDriver /
            // App\Console\Commands\PollP2POffers treat an empty book as
            // "nothing new" and move on, so this never interrupts the
            // scheduler or unrelated commands (/mempool, /escrow).
            Log::error('Failed to fetch RoboSats order book', ['err' => $e->getMessage()]);

            return [];
        }
    }
}
