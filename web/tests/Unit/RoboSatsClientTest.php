<?php

namespace Tests\Unit;

use App\Services\RoboSats\RoboSatsClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ReflectionProperty;
use Tests\TestCase;

class RoboSatsClientTest extends TestCase
{
    public function test_proxy_url_falls_back_to_config_when_not_passed_explicitly(): void
    {
        config(['services.robosats.proxy_url' => 'socks5h://tor:9050']);

        $client = new RoboSatsClient('http://robosats.test');

        // Guzzle's "proxy" transfer option isn't part of the request
        // message, so it can't be observed through Http::fake()'s recorded
        // Request objects — reflection is the only way to confirm the
        // constructor's config fallback actually resolved.
        $property = new ReflectionProperty($client, 'proxyUrl');
        $this->assertSame('socks5h://tor:9050', $property->getValue($client));
    }

    public function test_proxy_url_is_null_when_unconfigured(): void
    {
        config(['services.robosats.proxy_url' => null]);

        $client = new RoboSatsClient('http://robosats.test');

        $property = new ReflectionProperty($client, 'proxyUrl');
        $this->assertNull($property->getValue($client));
    }

    public function test_fetch_book_still_works_normally_with_a_proxy_configured(): void
    {
        Http::fake(['robosats.test/api/book/*' => Http::response([['id' => 1]], 200)]);

        $client = new RoboSatsClient('http://robosats.test', 'socks5h://tor:9050');

        $this->assertSame([['id' => 1]], $client->fetchBook('PYG'));
    }

    /** RoboSats' `currency` param is its own internal currency-table index, not ISO 4217 — see RoboSatsClient::CURRENCY_INDEX. */
    public function test_fetch_book_uses_robosats_internal_currency_index_not_iso_4217(): void
    {
        Http::fake(['robosats.test/api/book/*' => Http::response([], 200)]);

        $client = new RoboSatsClient('http://robosats.test');
        $client->fetchBook('PYG');
        Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'currency=35'));

        $client->fetchBook('USD');
        Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'currency=1'));
    }

    public function test_fetch_book_requests_both_buy_and_sell_orders(): void
    {
        Http::fake(['robosats.test/api/book/*' => Http::response([], 200)]);

        $client = new RoboSatsClient('http://robosats.test');
        $client->fetchBook('PYG');

        Http::assertSent(fn ($request) => str_contains((string) $request->url(), 'type=2'));
    }

    /** A coordinator with no open orders for this currency answers 404 with a JSON body — an empty book, not a failure worth logging. */
    public function test_fetch_book_treats_a_404_empty_book_response_as_no_offers_without_logging(): void
    {
        Log::spy();
        Http::fake(['robosats.test/api/book/*' => Http::response(['not_found' => 'No orders found, be the first to make one'], 404)]);

        $client = new RoboSatsClient('http://robosats.test');

        $this->assertSame([], $client->fetchBook('PYG'));
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
    }

    public function test_a_proxy_or_tor_connectivity_failure_returns_an_empty_book_instead_of_throwing(): void
    {
        Log::spy();
        Http::fake(function () {
            throw new ConnectionException('cURL error 7: Failed to connect to tor port 9050: Connection refused');
        });

        $client = new RoboSatsClient('http://robosats.test', 'socks5h://tor:9050');

        $this->assertSame([], $client->fetchBook('PYG'));
        Log::shouldHaveReceived('error')->once();
    }
}
