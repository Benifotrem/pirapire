<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CustomerTelegramLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard/link-telegram')->assertRedirect('/login');
    }

    public function test_authenticated_customer_gets_a_one_time_code(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')->get('/dashboard/link-telegram');

        $response->assertOk();
        $response->assertViewHas('code', fn ($code) => strlen($code) === 6);

        $code = $response->viewData('code');
        $entry = Cache::get('customer-telegram-link:'.$code);

        $this->assertSame($customer->id, $entry['customer_id']);
        $this->assertSame('pending', $entry['status']);
    }

    public function test_status_endpoint_reports_pending_for_a_fresh_code(): void
    {
        $customer = Customer::factory()->create();
        Cache::put('customer-telegram-link:ABC123', ['customer_id' => $customer->id, 'status' => 'pending'], 600);

        $this->actingAs($customer, 'customer')
            ->getJson('/dashboard/link-telegram/status/ABC123')
            ->assertOk()
            ->assertJsonPath('status', 'pending');
    }

    public function test_status_endpoint_reports_confirmed_once_the_bot_linked_it(): void
    {
        $customer = Customer::factory()->create();
        Cache::put('customer-telegram-link:ABC123', ['customer_id' => $customer->id, 'status' => 'confirmed'], 600);

        $this->actingAs($customer, 'customer')
            ->getJson('/dashboard/link-telegram/status/ABC123')
            ->assertOk()
            ->assertJsonPath('status', 'confirmed');
    }

    public function test_status_endpoint_reports_expired_for_an_unknown_code(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')
            ->getJson('/dashboard/link-telegram/status/NOPE99')
            ->assertOk()
            ->assertJsonPath('status', 'expired');
    }

    public function test_status_endpoint_refuses_to_leak_another_customers_pending_code(): void
    {
        $owner = Customer::factory()->create();
        $intruder = Customer::factory()->create();
        Cache::put('customer-telegram-link:ABC123', ['customer_id' => $owner->id, 'status' => 'confirmed'], 600);

        $this->actingAs($intruder, 'customer')
            ->getJson('/dashboard/link-telegram/status/ABC123')
            ->assertOk()
            ->assertJsonPath('status', 'expired');
    }
}
