<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;

/**
 * A P2P buy/sell offer, normalized to a common shape regardless of which
 * App\Contracts\P2PProviderInterface driver produced it — this is what
 * App\Services\P2P\AlertMatcher matches against and
 * App\Services\P2P\P2PMessageFormatter turns into a Telegram message, so
 * neither of them needs to know whether an offer came from RoboSats or
 * Mostro (or a future source).
 */
final class NormalizedP2POffer
{
    public function __construct(
        public readonly string $id,
        public readonly string $source,
        public readonly string $orderType,
        public readonly string $fiatAmount,
        public readonly string $fiatCurrency,
        public readonly ?int $estimatedSats,
        public readonly ?string $paymentMethod,
        public readonly ?string $makerReputation,
        public readonly string $url,
        public readonly ?string $actionCommand,
        public readonly ?CarbonImmutable $expiresAt,
    ) {}
}
