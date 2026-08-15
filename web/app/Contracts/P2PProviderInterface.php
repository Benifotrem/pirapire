<?php

namespace App\Contracts;

use App\DTOs\NormalizedP2POffer;

/**
 * A source of P2P Bitcoin buy/sell offers (RoboSats, Mostro, ...). Every
 * driver under App\Services\P2P\Drivers implements this so
 * App\Services\P2P\P2POfferAggregator can poll them interchangeably — it
 * never knows or cares which transport (HTTP, Nostr relays) a given
 * driver uses under the hood.
 */
interface P2PProviderInterface
{
    /**
     * Machine-readable identifier for this source — matches the values
     * accepted by the `alerts.source` column ('robosats', 'mostro', ...)
     * and the source used inside NormalizedP2POffer.
     */
    public function getProviderName(): string;

    /**
     * Fetches the current open offers for the given ISO-ish currency code
     * (e.g. 'PYG', 'USD'). Implementations are free to let network/parse
     * failures throw — App\Services\P2P\P2POfferAggregator is responsible
     * for catching them so one source going down doesn't take the others
     * with it.
     *
     * @return NormalizedP2POffer[]
     */
    public function fetchOffers(string $currency): array;

    /** The direct link shown to a subscriber for this offer. */
    public function formatOfferUrl(NormalizedP2POffer $offer): string;
}
