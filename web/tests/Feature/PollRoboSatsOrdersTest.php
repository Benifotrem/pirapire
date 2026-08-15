<?php

namespace Tests\Feature;

use App\Jobs\SendRoboSatsAlert;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\VipSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PollRoboSatsOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function fakeBook(array $orders): void
    {
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

    public function test_noops_when_robosats_is_not_configured(): void
    {
        config(['services.robosats.api_base_url' => null]);
        Queue::fake();

        $this->artisan('robosats:poll')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_first_poll_establishes_a_baseline_without_alerting(): void
    {
        config(['services.robosats.api_base_url' => 'http://robosats.test']);
        $this->fakeBook([$this->order(1), $this->order(2)]);

        $customer = Customer::factory()->create();
        Alert::factory()->create(['customer_id' => $customer->id, 'currency' => 'PYG', 'is_active' => true, 'order_type' => 'ANY']);

        Queue::fake();
        $this->artisan('robosats:poll')->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame(2, Cache::get('robosats:max-seen-order-id:PYG'));
    }

    public function test_dispatches_instantly_to_vip_subscribers_for_new_orders(): void
    {
        config(['services.robosats.api_base_url' => 'http://robosats.test']);
        Cache::forever('robosats:max-seen-order-id:PYG', 5);
        $this->fakeBook([$this->order(6)]);

        $customer = Customer::factory()->create();
        VipSubscription::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'active',
            'expires_at' => now()->addDays(10),
        ]);
        Alert::factory()->create(['customer_id' => $customer->id, 'currency' => 'PYG', 'is_active' => true, 'order_type' => 'ANY']);

        Queue::fake();
        $this->artisan('robosats:poll')->assertSuccessful();

        Queue::assertPushed(SendRoboSatsAlert::class, function (SendRoboSatsAlert $job) use ($customer) {
            return $job->telegramChatId === $customer->telegram_chat_id && $job->delay === null;
        });
    }

    public function test_delays_free_tier_subscribers(): void
    {
        config(['services.robosats.api_base_url' => 'http://robosats.test']);
        config(['services.alerts.free_tier_delay_minutes' => 10]);
        Cache::forever('robosats:max-seen-order-id:PYG', 5);
        $this->fakeBook([$this->order(6)]);

        $customer = Customer::factory()->create();
        Alert::factory()->create(['customer_id' => $customer->id, 'currency' => 'PYG', 'is_active' => true, 'order_type' => 'ANY']);

        Queue::fake();
        $this->artisan('robosats:poll')->assertSuccessful();

        Queue::assertPushed(SendRoboSatsAlert::class, function (SendRoboSatsAlert $job) {
            return $job->delay !== null;
        });
    }

    public function test_a_connectivity_failure_such_as_tor_being_down_does_not_fail_the_command(): void
    {
        config(['services.robosats.api_base_url' => 'http://robosats.test', 'services.robosats.proxy_url' => 'socks5h://tor:9050']);
        Http::fake(function () {
            throw new ConnectionException('cURL error 7: Failed to connect to tor port 9050: Connection refused');
        });

        Queue::fake();

        // robosats:poll must exit successfully even when Tor/the proxy is
        // unreachable — a non-zero exit here would show up as a failed
        // scheduled task, but must never take other Schedule entries
        // (vip-subscriptions:expire, escrow-jobs:cancel-expired) down with
        // it, since each runs as its own isolated process.
        $this->artisan('robosats:poll')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_does_not_alert_subscribers_without_telegram_linked(): void
    {
        config(['services.robosats.api_base_url' => 'http://robosats.test']);
        Cache::forever('robosats:max-seen-order-id:PYG', 5);
        $this->fakeBook([$this->order(6)]);

        $customer = Customer::factory()->create(['telegram_chat_id' => null]);
        Alert::factory()->create(['customer_id' => $customer->id, 'currency' => 'PYG', 'is_active' => true, 'order_type' => 'ANY']);

        Queue::fake();
        $this->artisan('robosats:poll')->assertSuccessful();

        Queue::assertNothingPushed();
    }
}
