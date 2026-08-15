<?php

namespace Tests\Unit\P2P;

use App\Services\Nostr\NostrRelayClient;
use App\Services\P2P\Drivers\MostroDriver;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class MostroDriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.mostro.relays' => ['wss://relay.mostro.test'],
            'services.mostro.pubkey' => 'mostro-pubkey-hex',
            'services.mostro.relay_timeout_seconds' => 5,
        ]);
    }

    private function event(array $tagOverrides = [], array $eventOverrides = []): array
    {
        // Keyed by tag name so $tagOverrides replaces a default tag
        // in-place instead of array_merge() just appending a second,
        // ignored ['k', ...] pair alongside the default one.
        $defaults = [
            'd' => ['d', 'order-1'],
            'k' => ['k', 'sell'],
            'f' => ['f', 'PYG'],
            's' => ['s', 'pending'],
            'fa' => ['fa', '150000'],
            'amt' => ['amt', '0'],
            'pm' => ['pm', 'PIX'],
        ];
        foreach ($tagOverrides as $tag) {
            $defaults[$tag[0]] = $tag;
        }
        $tags = array_values($defaults);

        return array_merge([
            'id' => 'event-id-abc',
            'pubkey' => 'mostro-pubkey-hex',
            'kind' => 38383,
            'tags' => $tags,
            'content' => '',
        ], $eventOverrides);
    }

    public function test_get_provider_name(): void
    {
        $driver = new MostroDriver(new NostrRelayClient);
        $this->assertSame('mostro', $driver->getProviderName());
    }

    public function test_returns_no_offers_when_relays_are_not_configured(): void
    {
        config(['services.mostro.relays' => []]);
        $driver = new MostroDriver($this->createMock(NostrRelayClient::class));

        $this->assertSame([], $driver->fetchOffers('PYG'));
    }

    public function test_returns_no_offers_when_pubkey_is_not_configured(): void
    {
        config(['services.mostro.pubkey' => null]);
        $driver = new MostroDriver($this->createMock(NostrRelayClient::class));

        $this->assertSame([], $driver->fetchOffers('PYG'));
    }

    public function test_normalizes_a_sell_order_event(): void
    {
        $relay = $this->createMock(NostrRelayClient::class);
        $relay->method('fetchEvents')->willReturn([$this->event()]);

        $offer = (new MostroDriver($relay))->fetchOffers('PYG')[0];

        // The offer id is the stable "d" tag (the order's own id), not
        // the Nostr event's own id — the latter changes every time
        // Mostro republishes the order with a new status. See this
        // class's docblock.
        $this->assertSame('order-1', $offer->id);
        $this->assertSame('mostro', $offer->source);
        $this->assertSame('SELL', $offer->orderType);
        $this->assertSame('150000', $offer->fiatAmount);
        $this->assertSame('PYG', $offer->fiatCurrency);
        $this->assertNull($offer->estimatedSats); // amt=0 means market price
        $this->assertSame('PIX', $offer->paymentMethod);
        // The link still uses the raw event id — that's what njump.me indexes.
        $this->assertSame('https://njump.me/event-id-abc', $offer->url);
        $this->assertSame('mostro-cli takesell -o order-1', $offer->actionCommand);
    }

    public function test_normalizes_a_buy_order_event_with_a_fixed_sats_amount(): void
    {
        $relay = $this->createMock(NostrRelayClient::class);
        $relay->method('fetchEvents')->willReturn([
            $this->event([['k', 'buy'], ['amt', '50000']]),
        ]);

        $offer = (new MostroDriver($relay))->fetchOffers('PYG')[0];

        $this->assertSame('BUY', $offer->orderType);
        $this->assertSame(50000, $offer->estimatedSats);
        $this->assertSame('mostro-cli takebuy -o order-1', $offer->actionCommand);
    }

    public function test_a_range_order_reports_a_min_max_fiat_amount(): void
    {
        // create_fiat_amt_array() in Mostro's own source emits a two-value
        // "fa" tag (min, max) for a still-pending range order.
        $relay = $this->createMock(NostrRelayClient::class);
        $relay->method('fetchEvents')->willReturn([
            $this->event([['fa', '50000', '200000']]),
        ]);

        $offer = (new MostroDriver($relay))->fetchOffers('PYG')[0];

        $this->assertSame('50000-200000', $offer->fiatAmount);
    }

    public function test_multiple_payment_methods_are_joined(): void
    {
        $relay = $this->createMock(NostrRelayClient::class);
        $relay->method('fetchEvents')->willReturn([
            $this->event([['pm', 'PIX', 'Bank transfer']]),
        ]);

        $offer = (new MostroDriver($relay))->fetchOffers('PYG')[0];

        $this->assertSame('PIX, Bank transfer', $offer->paymentMethod);
    }

    public function test_parses_the_expiration_tag(): void
    {
        $relay = $this->createMock(NostrRelayClient::class);
        $relay->method('fetchEvents')->willReturn([
            $this->event(tagOverrides: [['expiration', '1893456000']]),
        ]);

        $offer = (new MostroDriver($relay))->fetchOffers('PYG')[0];

        $this->assertNotNull($offer->expiresAt);
        $this->assertSame(1893456000, $offer->expiresAt->getTimestamp());
    }

    public function test_filters_out_offers_for_a_different_currency(): void
    {
        $relay = $this->createMock(NostrRelayClient::class);
        $relay->method('fetchEvents')->willReturn([$this->event([['f', 'USD']])]);

        $this->assertSame([], (new MostroDriver($relay))->fetchOffers('PYG'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonOpenStatuses(): array
    {
        // mostro_core::order::Status has ~18 variants; only "pending"
        // means "still open for someone to take" — everything else is
        // mid-trade or finished and shouldn't show up as an offer.
        return [
            'completed' => ['completed'],
            'in-progress' => ['in-progress'],
            'active' => ['active'], // not "still open" despite the name — see this class's docblock
            'waiting-payment' => ['waiting-payment'],
        ];
    }

    #[DataProvider('nonOpenStatuses')]
    public function test_filters_out_non_open_statuses(string $status): void
    {
        $relay = $this->createMock(NostrRelayClient::class);
        $relay->method('fetchEvents')->willReturn([$this->event([['s', $status]])]);

        $this->assertSame([], (new MostroDriver($relay))->fetchOffers('PYG'));
    }

    public function test_skips_events_missing_the_d_tag_instead_of_erroring(): void
    {
        $relay = $this->createMock(NostrRelayClient::class);
        $relay->method('fetchEvents')->willReturn([
            ['id' => 'event-without-d-tag', 'tags' => [['f', 'PYG'], ['s', 'pending']]], // no 'd'
            $this->event(),
        ]);

        $offers = (new MostroDriver($relay))->fetchOffers('PYG');

        $this->assertCount(1, $offers);
    }

    public function test_skips_events_missing_an_id_instead_of_erroring(): void
    {
        $relay = $this->createMock(NostrRelayClient::class);
        $relay->method('fetchEvents')->willReturn([
            ['tags' => [['f', 'PYG'], ['s', 'pending']]], // no 'id'
            $this->event(),
        ]);

        $offers = (new MostroDriver($relay))->fetchOffers('PYG');

        $this->assertCount(1, $offers);
    }

    public function test_deduplicates_the_same_order_seen_on_multiple_relays(): void
    {
        config(['services.mostro.relays' => ['wss://relay-a.test', 'wss://relay-b.test']]);

        $relay = $this->createMock(NostrRelayClient::class);
        $relay->method('fetchEvents')->willReturn([$this->event()]);

        $offers = (new MostroDriver($relay))->fetchOffers('PYG');

        $this->assertCount(1, $offers);
    }

    public function test_one_dead_relay_does_not_prevent_offers_from_a_healthy_one(): void
    {
        Log::spy();
        config(['services.mostro.relays' => ['wss://dead.test', 'wss://alive.test']]);

        $relay = $this->createMock(NostrRelayClient::class);
        $relay->method('fetchEvents')->willReturnCallback(function (string $relayUrl) {
            if ($relayUrl === 'wss://dead.test') {
                throw new RuntimeException('Could not connect to Nostr relay wss://dead.test: connection refused');
            }

            return [$this->event()];
        });

        $offers = (new MostroDriver($relay))->fetchOffers('PYG');

        $this->assertCount(1, $offers);
        Log::shouldHaveReceived('warning')->once();
    }
}
