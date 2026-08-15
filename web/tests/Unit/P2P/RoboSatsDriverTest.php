<?php

namespace Tests\Unit\P2P;

use App\Services\P2P\Drivers\RoboSatsDriver;
use App\Services\RoboSats\RoboSatsClient;
use Tests\TestCase;

class RoboSatsDriverTest extends TestCase
{
    private function order(array $overrides = []): array
    {
        return array_merge([
            'id' => 7,
            'type' => 0, // BUY
            'amount' => '100000',
            'min_amount' => null,
            'max_amount' => null,
            'payment_method' => 'Bank transfer',
            'price' => '1000000',
            'premium' => '2.5',
            'maker_nick' => 'satoshi',
        ], $overrides);
    }

    public function test_get_provider_name(): void
    {
        $driver = new RoboSatsDriver(new RoboSatsClient('http://robosats.test'));
        $this->assertSame('robosats', $driver->getProviderName());
    }

    public function test_returns_no_offers_when_unconfigured(): void
    {
        $driver = new RoboSatsDriver(new RoboSatsClient(null));
        $this->assertSame([], $driver->fetchOffers('PYG'));
    }

    public function test_normalizes_a_buy_order_and_estimates_sats_from_price(): void
    {
        $client = $this->createMock(RoboSatsClient::class);
        $client->method('isConfigured')->willReturn(true);
        $client->method('fetchBook')->willReturn([$this->order()]);

        $offers = (new RoboSatsDriver($client))->fetchOffers('PYG');

        $this->assertCount(1, $offers);
        $offer = $offers[0];
        $this->assertSame('7', $offer->id);
        $this->assertSame('robosats', $offer->source);
        $this->assertSame('BUY', $offer->orderType);
        $this->assertSame('100000', $offer->fiatAmount);
        $this->assertSame('PYG', $offer->fiatCurrency);
        // 100000 / 1000000 BTC * 100_000_000 sats/BTC = 10_000_000 sats
        $this->assertSame(10_000_000, $offer->estimatedSats);
        $this->assertSame('Bank transfer', $offer->paymentMethod);
        $this->assertSame('https://robosats.com/order/7', $offer->url);
        $this->assertNull($offer->actionCommand);
    }

    public function test_normalizes_a_sell_order(): void
    {
        $client = $this->createMock(RoboSatsClient::class);
        $client->method('isConfigured')->willReturn(true);
        $client->method('fetchBook')->willReturn([$this->order(['type' => 1])]);

        $offer = (new RoboSatsDriver($client))->fetchOffers('PYG')[0];

        $this->assertSame('SELL', $offer->orderType);
    }

    public function test_falls_back_to_a_range_string_and_null_sats_estimate_when_amount_is_missing(): void
    {
        $client = $this->createMock(RoboSatsClient::class);
        $client->method('isConfigured')->willReturn(true);
        $client->method('fetchBook')->willReturn([
            $this->order(['amount' => null, 'min_amount' => 50000, 'max_amount' => 200000]),
        ]);

        $offer = (new RoboSatsDriver($client))->fetchOffers('PYG')[0];

        $this->assertSame('50000-200000', $offer->fiatAmount);
        $this->assertNull($offer->estimatedSats);
    }

    public function test_format_offer_url(): void
    {
        $client = $this->createMock(RoboSatsClient::class);
        $client->method('isConfigured')->willReturn(true);
        $client->method('fetchBook')->willReturn([$this->order(['id' => 99])]);

        $offer = (new RoboSatsDriver($client))->fetchOffers('PYG')[0];
        $driver = new RoboSatsDriver($client);

        $this->assertSame('https://robosats.com/order/99', $driver->formatOfferUrl($offer));
    }
}
