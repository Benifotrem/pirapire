<?php

namespace Tests\Unit\P2P;

use App\Contracts\P2PProviderInterface;
use App\DTOs\NormalizedP2POffer;
use App\Services\P2P\P2POfferAggregator;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class P2POfferAggregatorTest extends TestCase
{
    private function offer(string $source, string $id = '1'): NormalizedP2POffer
    {
        return new NormalizedP2POffer(
            id: $id,
            source: $source,
            orderType: 'BUY',
            fiatAmount: '1000',
            fiatCurrency: 'PYG',
            estimatedSats: null,
            paymentMethod: null,
            makerReputation: null,
            url: 'https://example.test',
            actionCommand: null,
            expiresAt: null,
        );
    }

    private function driver(string $name, array $offers): P2PProviderInterface
    {
        return new class($name, $offers) implements P2PProviderInterface
        {
            public function __construct(private string $name, private array $offers) {}

            public function getProviderName(): string
            {
                return $this->name;
            }

            public function fetchOffers(string $currency): array
            {
                return $this->offers;
            }

            public function formatOfferUrl(NormalizedP2POffer $offer): string
            {
                return $offer->url;
            }
        };
    }

    private function failingDriver(string $name): P2PProviderInterface
    {
        return new class($name) implements P2PProviderInterface
        {
            public function __construct(private string $name) {}

            public function getProviderName(): string
            {
                return $this->name;
            }

            public function fetchOffers(string $currency): array
            {
                throw new RuntimeException("{$this->name} is down");
            }

            public function formatOfferUrl(NormalizedP2POffer $offer): string
            {
                return $offer->url;
            }
        };
    }

    public function test_collects_offers_from_every_registered_driver(): void
    {
        $aggregator = new P2POfferAggregator([
            $this->driver('robosats', [$this->offer('robosats', '1')]),
            $this->driver('mostro', [$this->offer('mostro', '2')]),
        ]);

        $offers = $aggregator->collect('PYG');

        $this->assertCount(2, $offers);
    }

    public function test_a_failing_driver_is_skipped_without_affecting_the_others(): void
    {
        Log::spy();

        $aggregator = new P2POfferAggregator([
            $this->failingDriver('robosats'),
            $this->driver('mostro', [$this->offer('mostro', '2')]),
        ]);

        $offers = $aggregator->collect('PYG');

        $this->assertCount(1, $offers);
        $this->assertSame('mostro', $offers[0]->source);
        Log::shouldHaveReceived('error')->once();
    }

    public function test_all_registered_drivers_failing_returns_an_empty_array_without_throwing(): void
    {
        Log::spy();

        $aggregator = new P2POfferAggregator([
            $this->failingDriver('robosats'),
            $this->failingDriver('mostro'),
        ]);

        $this->assertSame([], $aggregator->collect('PYG'));
    }

    public function test_sources_filter_only_includes_the_requested_driver(): void
    {
        $aggregator = new P2POfferAggregator([
            $this->driver('robosats', [$this->offer('robosats', '1')]),
            $this->driver('mostro', [$this->offer('mostro', '2')]),
        ]);

        $offers = $aggregator->collect('PYG', ['mostro']);

        $this->assertCount(1, $offers);
        $this->assertSame('mostro', $offers[0]->source);
    }

    public function test_available_sources_lists_every_registered_driver_name(): void
    {
        $aggregator = new P2POfferAggregator([
            $this->driver('robosats', []),
            $this->driver('mostro', []),
        ]);

        $this->assertSame(['robosats', 'mostro'], $aggregator->availableSources());
    }
}
