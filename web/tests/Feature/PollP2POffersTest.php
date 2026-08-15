<?php

namespace Tests\Feature;

use App\Jobs\SendP2POfferAlert;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\VipSubscription;
use App\Services\Nostr\NostrRelayClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PollP2POffersTest extends TestCase
{
    use RefreshDatabase;

    private function fakeRoboSatsBook(array $orders): void
    {
        config(['services.robosats.api_base_url' => 'http://robosats.test']);
        Http::fake([
            'robosats.test/book/*' => Http::response($orders, 200),
        ]);
    }

    private function order(int $id, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'type' => 0,
            'amount' => '100000',
            'min_amount' => null,
            'max_amount' => null,
            'payment_method' => 'Bank transfer',
            'price' => '1000000',
            'premium' => '2.5',
            'maker_nick' => 'satoshi',
        ], $overrides);
    }

    public function test_noops_with_a_warning_when_no_source_is_configured(): void
    {
        config(['services.robosats.api_base_url' => null, 'services.mostro.relays' => []]);
        Queue::fake();

        $this->artisan('p2p:poll')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_first_poll_establishes_a_baseline_without_alerting(): void
    {
        $this->fakeRoboSatsBook([$this->order(1), $this->order(2)]);

        $customer = Customer::factory()->create();
        Alert::factory()->create(['customer_id' => $customer->id, 'currency' => 'PYG', 'source' => 'all', 'order_type' => 'ANY']);

        Queue::fake();
        $this->artisan('p2p:poll')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertNotNull(Cache::get('p2p:seen-offer-ids:robosats:PYG'));
    }

    public function test_dispatches_instantly_to_vip_subscribers_for_new_offers(): void
    {
        Cache::forever('p2p:seen-offer-ids:robosats:PYG', ['5']);
        $this->fakeRoboSatsBook([$this->order(6)]);

        $customer = Customer::factory()->create();
        VipSubscription::factory()->create(['customer_id' => $customer->id, 'status' => 'active', 'expires_at' => now()->addDays(10)]);
        Alert::factory()->create(['customer_id' => $customer->id, 'currency' => 'PYG', 'source' => 'all', 'order_type' => 'ANY']);

        Queue::fake();
        $this->artisan('p2p:poll')->assertSuccessful();

        Queue::assertPushed(SendP2POfferAlert::class, function (SendP2POfferAlert $job) use ($customer) {
            return $job->telegramChatId === $customer->telegram_chat_id && $job->delay === null;
        });
    }

    public function test_delays_free_tier_subscribers(): void
    {
        config(['services.alerts.free_tier_delay_minutes' => 10]);
        Cache::forever('p2p:seen-offer-ids:robosats:PYG', ['5']);
        $this->fakeRoboSatsBook([$this->order(6)]);

        $customer = Customer::factory()->create();
        Alert::factory()->create(['customer_id' => $customer->id, 'currency' => 'PYG', 'source' => 'all', 'order_type' => 'ANY']);

        Queue::fake();
        $this->artisan('p2p:poll')->assertSuccessful();

        Queue::assertPushed(SendP2POfferAlert::class, fn (SendP2POfferAlert $job) => $job->delay !== null);
    }

    public function test_does_not_alert_subscribers_without_telegram_linked(): void
    {
        Cache::forever('p2p:seen-offer-ids:robosats:PYG', ['5']);
        $this->fakeRoboSatsBook([$this->order(6)]);

        $customer = Customer::factory()->create(['telegram_chat_id' => null]);
        Alert::factory()->create(['customer_id' => $customer->id, 'currency' => 'PYG', 'source' => 'all', 'order_type' => 'ANY']);

        Queue::fake();
        $this->artisan('p2p:poll')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_an_alert_scoped_to_mostro_ignores_new_robosats_offers(): void
    {
        Cache::forever('p2p:seen-offer-ids:robosats:PYG', ['5']);
        $this->fakeRoboSatsBook([$this->order(6)]);

        $customer = Customer::factory()->create();
        Alert::factory()->create(['customer_id' => $customer->id, 'currency' => 'PYG', 'source' => 'mostro', 'order_type' => 'ANY']);

        Queue::fake();
        $this->artisan('p2p:poll')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_a_connectivity_failure_on_one_source_does_not_fail_the_command(): void
    {
        config(['services.robosats.api_base_url' => 'http://robosats.test', 'services.robosats.proxy_url' => 'socks5h://tor:9050']);
        Http::fake(function () {
            throw new ConnectionException('cURL error 7: Failed to connect to tor port 9050: Connection refused');
        });

        Queue::fake();

        $this->artisan('p2p:poll')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_mostro_offers_are_included_via_the_nostr_relay_client(): void
    {
        config([
            'services.mostro.relays' => ['wss://relay.mostro.test'],
            'services.mostro.pubkey' => 'mostro-pubkey-hex',
        ]);

        // Baseline already established for a prior poll — this offer is
        // "new" as of the poll under test, matching how the RoboSats
        // cases above pre-seed the cache instead of relying on a real
        // first/second artisan() round trip.
        Cache::forever('p2p:seen-offer-ids:mostro:PYG', []);

        $this->mock(NostrRelayClient::class, function ($mock) {
            $mock->shouldReceive('fetchEvents')->andReturn([[
                'id' => 'mostro-event-1',
                'tags' => [
                    ['d', 'order-1'], ['k', 'sell'], ['f', 'PYG'], ['s', 'pending'],
                    ['fa', '150000'], ['amt', '0'], ['pm', 'PIX'],
                ],
            ]]);
        });

        $customer = Customer::factory()->create();
        Alert::factory()->create(['customer_id' => $customer->id, 'currency' => 'PYG', 'source' => 'mostro', 'order_type' => 'ANY']);

        Queue::fake();
        $this->artisan('p2p:poll')->assertSuccessful();

        Queue::assertPushed(SendP2POfferAlert::class, function (SendP2POfferAlert $job) use ($customer) {
            return $job->telegramChatId === $customer->telegram_chat_id
                && str_contains($job->message, 'Mostro')
                && str_contains($job->message, 'mostro-cli takesell -o mostro-event-1');
        });
    }
}
