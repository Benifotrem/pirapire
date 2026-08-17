<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_customer_can_view_their_dashboard(): void
    {
        $customer = Customer::factory()->create();
        Alert::factory()->create(['customer_id' => $customer->id]);

        $response = $this->actingAs($customer, 'customer')->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('alerts', fn ($alerts) => $alerts->count() === 1);
    }

    public function test_customer_can_create_an_alert(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')->post('/dashboard/alerts', [
            'currency' => 'PYG',
            'order_type' => 'BUY',
            'min_amount' => 10000,
            'max_amount' => 500000,
        ])->assertRedirect();

        $this->assertDatabaseHas('alerts', [
            'customer_id' => $customer->id,
            'currency' => 'PYG',
            'order_type' => 'BUY',
        ]);
    }

    public function test_customer_cannot_delete_another_customers_alert(): void
    {
        $owner = Customer::factory()->create();
        $intruder = Customer::factory()->create();
        $alert = Alert::factory()->create(['customer_id' => $owner->id]);

        $this->actingAs($intruder, 'customer')
            ->delete("/dashboard/alerts/{$alert->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('alerts', ['id' => $alert->id]);
    }

    public function test_customer_can_toggle_their_own_alert(): void
    {
        $customer = Customer::factory()->create();
        $alert = Alert::factory()->create(['customer_id' => $customer->id, 'is_active' => true]);

        $this->actingAs($customer, 'customer')
            ->patch("/dashboard/alerts/{$alert->id}/toggle")
            ->assertRedirect();

        $this->assertFalse($alert->fresh()->is_active);
    }

    public function test_free_tier_status_reads_instant_when_no_delay_is_configured(): void
    {
        config(['services.alerts.free_tier_delay_minutes' => 0]);
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')->get('/dashboard');

        $response->assertSee(__('site.dashboard.free_detail_instant'));
        $response->assertDontSee('minutos de retraso');
    }

    /** Free-tier copy is meant to flip back on when FREE_TIER_DELAY_MINUTES is reintroduced for a commercial VIP tier — see README "Fase actual: alertas gratis instantáneas". */
    public function test_free_tier_status_shows_the_configured_delay_when_one_is_set(): void
    {
        config(['services.alerts.free_tier_delay_minutes' => 10]);
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')->get('/dashboard');

        $response->assertSee(__('site.dashboard.free_detail', ['minutes' => 10]));
    }
}
