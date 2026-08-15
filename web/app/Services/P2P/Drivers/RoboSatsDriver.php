<?php

namespace App\Services\P2P\Drivers;

use App\Contracts\P2PProviderInterface;
use App\DTOs\NormalizedP2POffer;
use App\Services\RoboSats\RoboSatsClient;

/**
 * Adapts App\Services\RoboSats\RoboSatsClient (a thin wrapper around
 * RoboSats' public order-book HTTP endpoint) to P2PProviderInterface.
 * RoboSatsClient already swallows its own connectivity failures and
 * returns an empty book (see its docblock) — this driver never throws
 * either, it just maps whatever comes back.
 */
class RoboSatsDriver implements P2PProviderInterface
{
    private const SATS_PER_BTC = 100_000_000;

    public function __construct(private readonly RoboSatsClient $client) {}

    public function getProviderName(): string
    {
        return 'robosats';
    }

    /** @return NormalizedP2POffer[] */
    public function fetchOffers(string $currency): array
    {
        if (! $this->client->isConfigured()) {
            return [];
        }

        return array_map(
            fn (array $order) => $this->normalize($order, $currency),
            $this->client->fetchBook($currency),
        );
    }

    public function formatOfferUrl(NormalizedP2POffer $offer): string
    {
        return "https://robosats.com/order/{$offer->id}";
    }

    /** @param array<string, mixed> $order */
    private function normalize(array $order, string $currency): NormalizedP2POffer
    {
        $orderType = (int) ($order['type'] ?? 0) === 0 ? 'BUY' : 'SELL';
        $amount = $order['amount'] ?? null;
        $fiatAmount = $amount !== null
            ? (string) $amount
            : (($order['min_amount'] ?? '?').'-'.($order['max_amount'] ?? '?'));

        $price = (float) ($order['price'] ?? 0);
        $estimatedSats = ($amount !== null && $price > 0)
            ? (int) round(((float) $amount / $price) * self::SATS_PER_BTC)
            : null;

        $id = (string) ($order['id'] ?? '');

        return new NormalizedP2POffer(
            id: $id,
            source: $this->getProviderName(),
            orderType: $orderType,
            fiatAmount: $fiatAmount,
            fiatCurrency: $currency,
            estimatedSats: $estimatedSats,
            paymentMethod: $order['payment_method'] ?? null,
            // RoboSats' public /book/ endpoint doesn't expose a
            // reputation score (only a maker nickname and status like
            // "Active") — nothing numeric or comparable to surface here.
            makerReputation: null,
            url: "https://robosats.com/order/{$id}",
            actionCommand: null,
            expiresAt: null,
        );
    }
}
