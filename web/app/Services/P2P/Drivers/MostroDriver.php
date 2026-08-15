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
 * Tag layout verified against Mostro's own source
 * (github.com/MostroP2P/mostro, src/nip33.rs — order_to_tags() and
 * create_fiat_amt_array()) rather than guessed: `d` (the order's stable
 * UUID — NOT the same as the Nostr event's own `id`, see below), `k`
 * (order kind, "buy"/"sell" — the maker's side), `f` (fiat currency),
 * `s` (status — mostro_core::order::Status has ~18 variants; only
 * "pending" means "still open for someone to take", the rest are
 * mid-trade/finished), `fa` (fiat amount — ONE value for a fixed-amount
 * order, or a [min, max] PAIR for a range order while pending), `amt`
 * (sats amount, 0/absent for market-price orders), `pm` (payment
 * method(s) — can be more than one), `expiration` (NIP-40 unix
 * timestamp). Parsing is defensive throughout — an event missing a
 * field we need is skipped rather than raised as an error, since relay
 * data isn't guaranteed to be well-formed.
 *
 * Important: Mostro orders are NIP-33 *replaceable* events — every
 * status change republishes the same order with a brand new event `id`
 * (it's a hash of the new content) but the same `d` tag. Using the
 * event's own `id` as this driver's offer id would make every status
 * update look like a distinct "new" offer to
 * App\Console\Commands\PollP2POffers's dedup logic — that's why `d`,
 * not `id`, is what ends up in NormalizedP2POffer::$id and in the
 * mostro-cli command. The event's own `id` is still used for the
 * njump.me link, since that's what njump.me actually indexes.
 */
class MostroDriver implements P2PProviderInterface
{
    private const ORDER_EVENT_KIND = 38383;

    private const OPEN_STATUS = 'pending';

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
        // The njump.me link needs the raw Nostr event id (a hash that
        // changes every time Mostro republishes the order), which isn't
        // $offer->id here — that's the order's stable `d` tag instead
        // (see this class's docblock). normalize() already builds the
        // correct link into $offer->url from the event id it had in
        // hand, so this just returns it rather than trying to
        // reconstruct something we no longer have.
        return $offer->url;
    }

    /** @param  array<string, mixed>  $event */
    private function normalize(array $event, string $currency): ?NormalizedP2POffer
    {
        $eventId = $event['id'] ?? null;
        if (! is_string($eventId) || $eventId === '') {
            return null;
        }

        $tags = $this->tagMap(is_array($event['tags'] ?? null) ? $event['tags'] : []);

        $status = $tags['s'][0] ?? null;
        if ($status !== self::OPEN_STATUS) {
            return null;
        }

        $orderId = $tags['d'][0] ?? null;
        if (! is_string($orderId) || $orderId === '') {
            return null;
        }

        $fiatCode = strtoupper($tags['f'][0] ?? '');
        if ($fiatCode === '' || $fiatCode !== strtoupper($currency)) {
            return null;
        }

        $orderType = strtolower($tags['k'][0] ?? '') === 'sell' ? 'SELL' : 'BUY';
        $sats = isset($tags['amt'][0]) ? (int) $tags['amt'][0] : null;
        $expirationRaw = $tags['expiration'][0] ?? null;
        $expiresAt = ($expirationRaw !== null && ctype_digit($expirationRaw))
            ? CarbonImmutable::createFromTimestamp((int) $expirationRaw)
            : null;

        // create_fiat_amt_array() (nip33.rs) emits one value for a fixed
        // amount, or [min, max] for a still-open range order.
        $fiatAmountParts = $tags['fa'] ?? [];
        $fiatAmount = count($fiatAmountParts) >= 2
            ? "{$fiatAmountParts[0]}-{$fiatAmountParts[1]}"
            : ($fiatAmountParts[0] ?? '?');

        $paymentMethod = ! empty($tags['pm']) ? implode(', ', $tags['pm']) : null;

        return new NormalizedP2POffer(
            id: $orderId,
            source: $this->getProviderName(),
            orderType: $orderType,
            fiatAmount: $fiatAmount,
            fiatCurrency: strtoupper($currency),
            estimatedSats: ($sats !== null && $sats > 0) ? $sats : null,
            paymentMethod: $paymentMethod,
            // Mostro's order event doesn't carry a reputation score
            // itself (that lives in a separate rating system) — left
            // null rather than guessed.
            makerReputation: null,
            url: "https://njump.me/{$eventId}",
            actionCommand: $orderType === 'SELL' ? "mostro-cli takesell -o {$orderId}" : "mostro-cli takebuy -o {$orderId}",
            expiresAt: $expiresAt,
        );
    }

    /**
     * @param  array<int, mixed>  $tags  raw Nostr tags: [["f","PYG"], ["fa","1000","5000"], ...]
     * @return array<string, array<int, string>> every value (after the name) seen per tag name
     */
    private function tagMap(array $tags): array
    {
        $map = [];
        foreach ($tags as $tag) {
            if (is_array($tag) && isset($tag[0]) && is_string($tag[0]) && count($tag) > 1 && ! isset($map[$tag[0]])) {
                $map[$tag[0]] = array_map('strval', array_slice($tag, 1));
            }
        }

        return $map;
    }
}
