<?php

namespace App\Services\P2P\Drivers;

use App\Contracts\P2PProviderInterface;
use App\DTOs\NormalizedP2POffer;
use App\Services\Nostr\NostrRelayClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads open orders from Mostro (a P2P Lightning exchange with no HTTP
 * API of its own — orders are Nostr events, kind 38383, published by the
 * Mostro instance's own pubkey) via App\Services\Nostr\NostrRelayClient.
 *
 * Tag layout follows Mostro's published order-event NIP: `d` (order id),
 * `k` (order kind, "buy"/"sell" — the maker's side), `f` (fiat currency),
 * `s` (status), `fa` (fiat amount), `amt` (sats amount, 0/absent for
 * market-price orders), `pm` (payment method), `premium`, `expiration`
 * (NIP-40 unix timestamp). Parsing is defensive throughout — an event
 * missing a field we need is skipped rather than raised as an error,
 * since relay data isn't guaranteed to be well-formed.
 */
class MostroDriver implements P2PProviderInterface
{
    private const ORDER_EVENT_KIND = 38383;

    private const OPEN_STATUSES = ['pending', 'active'];

    public function __construct(private readonly NostrRelayClient $relay) {}

    public function getProviderName(): string
    {
        return 'mostro';
    }

    /** @return NormalizedP2POffer[] */
    public function fetchOffers(string $currency): array
    {
        $relays = config('services.mostro.relays', []);
        $pubkey = config('services.mostro.pubkey');
        if (empty($relays) || ! $pubkey) {
            return [];
        }

        $filter = [
            'kinds' => [self::ORDER_EVENT_KIND],
            'authors' => [$pubkey],
            'limit' => 100,
        ];
        $timeout = (int) config('services.mostro.relay_timeout_seconds', 8);

        $offers = [];
        $seenIds = [];

        foreach ($relays as $relayUrl) {
            try {
                $events = $this->relay->fetchEvents($relayUrl, $filter, $timeout);
            } catch (Throwable $e) {
                // One relay being down (or a Tor/network hiccup reaching
                // it) shouldn't blank out offers the other configured
                // relays already have — skip it and keep going.
                Log::warning('Mostro relay unreachable, skipping', ['relay' => $relayUrl, 'error' => $e->getMessage()]);

                continue;
            }

            foreach ($events as $event) {
                $offer = $this->normalize($event, $currency);
                if ($offer === null || isset($seenIds[$offer->id])) {
                    continue;
                }
                $seenIds[$offer->id] = true;
                $offers[] = $offer;
            }
        }

        return $offers;
    }

    public function formatOfferUrl(NormalizedP2POffer $offer): string
    {
        // njump.me renders any Nostr event id as a readable page — the
        // closest thing to a "direct link" Mostro orders have, since
        // Mostro itself has no web order-detail page (orders are only
        // actionable via mostro-cli or a Mostro-aware Nostr client).
        return "https://njump.me/{$offer->id}";
    }

    /** @param  array<string, mixed>  $event */
    private function normalize(array $event, string $currency): ?NormalizedP2POffer
    {
        $id = $event['id'] ?? null;
        if (! is_string($id) || $id === '') {
            return null;
        }

        $tags = $this->tagMap(is_array($event['tags'] ?? null) ? $event['tags'] : []);

        $status = $tags['s'] ?? null;
        if ($status !== null && ! in_array($status, self::OPEN_STATUSES, true)) {
            return null;
        }

        $fiatCode = strtoupper($tags['f'] ?? '');
        if ($fiatCode === '' || $fiatCode !== strtoupper($currency)) {
            return null;
        }

        $orderType = strtolower($tags['k'] ?? '') === 'sell' ? 'SELL' : 'BUY';
        $sats = isset($tags['amt']) ? (int) $tags['amt'] : null;
        $expiresAt = isset($tags['expiration']) && ctype_digit($tags['expiration'])
            ? CarbonImmutable::createFromTimestamp((int) $tags['expiration'])
            : null;

        return new NormalizedP2POffer(
            id: $id,
            source: $this->getProviderName(),
            orderType: $orderType,
            fiatAmount: $tags['fa'] ?? '?',
            fiatCurrency: strtoupper($currency),
            estimatedSats: ($sats !== null && $sats > 0) ? $sats : null,
            paymentMethod: $tags['pm'] ?? null,
            // Mostro's order event doesn't carry a reputation score
            // itself (that lives in a separate rating system) — left
            // null rather than guessed.
            makerReputation: null,
            url: "https://njump.me/{$id}",
            actionCommand: $orderType === 'SELL' ? "mostro-cli takesell -o {$id}" : "mostro-cli takebuy -o {$id}",
            expiresAt: $expiresAt,
        );
    }

    /**
     * @param  array<int, mixed>  $tags  raw Nostr tags: [["f","PYG"], ["k","sell"], ...]
     * @return array<string, string> first value seen per tag name
     */
    private function tagMap(array $tags): array
    {
        $map = [];
        foreach ($tags as $tag) {
            if (is_array($tag) && isset($tag[0], $tag[1]) && is_string($tag[0]) && ! isset($map[$tag[0]])) {
                $map[$tag[0]] = (string) $tag[1];
            }
        }

        return $map;
    }
}
