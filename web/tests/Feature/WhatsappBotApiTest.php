<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Customer;
use App\Models\VipSubscription;
use App\Services\Lightning\LnbitsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsappBotApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.whatsapp_bot.api_token' => 'test-bot-token']);
    }

    private function withBotToken()
    {
        return $this->withHeader('Authorization', 'Bearer test-bot-token');
    }

    public function test_requests_without_the_bot_token_are_rejected(): void
    {
        $this->getJson('/api/alerts/subscribers?currency=PYG')->assertStatus(401);
    }

    public function test_requests_with_the_wrong_token_are_rejected(): void
    {
        $this->withHeader('Authorization', 'Bearer wrong')
            ->getJson('/api/alerts/subscribers?currency=PYG')
            ->assertStatus(401);
    }

    public function test_alert_subscribers_only_returns_active_alerts_for_the_requested_currency(): void
    {
        $customer = Customer::factory()->create(['whatsapp_number' => '595981111111@s.whatsapp.net']);
        Alert::factory()->create(['customer_id' => $customer->id, 'currency' => 'PYG', 'is_active' => true]);
        Alert::factory()->create(['customer_id' => $customer->id, 'currency' => 'USD', 'is_active' => true]);
        Alert::factory()->create(['customer_id' => $customer->id, 'currency' => 'PYG', 'is_active' => false]);

        $response = $this->withBotToken()->getJson('/api/alerts/subscribers?currency=PYG');

        $response->assertOk()->assertJsonCount(1);
        $response->assertJsonFragment(['whatsapp_number' => '595981111111@s.whatsapp.net']);
    }

    public function test_vip_lookup_reports_active_subscription(): void
    {
        $customer = Customer::factory()->create(['whatsapp_number' => '595982222222@s.whatsapp.net']);
        VipSubscription::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'active',
            'expires_at' => now()->addDays(10),
        ]);

        $this->withBotToken()
            ->getJson('/api/vip/595982222222@s.whatsapp.net')
            ->assertOk()
            ->assertJson(['is_vip' => true]);
    }

    public function test_vip_lookup_reports_false_for_unknown_number(): void
    {
        $this->withBotToken()
            ->getJson('/api/vip/595989999999@s.whatsapp.net')
            ->assertOk()
            ->assertJson(['is_vip' => false, 'expires_at' => null]);
    }

    public function test_escrow_job_can_be_created_via_the_api(): void
    {
        $this->mock(LnbitsClient::class, function ($mock) {
            $mock->shouldReceive('createHoldInvoice')->once()->andReturn(['payment_request' => 'lnbc1testinvoice']);
        });

        $response = $this->withBotToken()->postJson('/api/escrow/jobs', [
            'whatsapp_number' => '595983333333@s.whatsapp.net',
            'amount_sats' => 50000,
            'description' => 'Traducción de documento',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('status', 'created');
        $response->assertJsonPath('amount_sats', 50000);
        $response->assertJsonPath('fee_sats', 750);

        $this->assertDatabaseHas('customers', ['whatsapp_number' => '595983333333@s.whatsapp.net']);
    }
}
