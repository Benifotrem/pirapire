<?php

namespace Tests\Feature\MiniApp;

use App\Models\Alert;
use App\Models\Customer;
use App\Models\EscrowJob;
use App\Services\Lightning\LnbitsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CustomerMiniAppTest extends TestCase
{
    use RefreshDatabase;

    private const BOT_TOKEN = 'customer-bot-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram_customer_bot.bot_token' => self::BOT_TOKEN]);
    }

    private function initDataFor(int $userId, string $botToken = self::BOT_TOKEN): string
    {
        $fields = [
            'auth_date' => (string) time(),
            'user' => json_encode(['id' => $userId, 'first_name' => 'Sat']),
        ];
        ksort($fields);
        $checkString = collect($fields)->map(fn ($v, $k) => "{$k}={$v}")->implode("\n");
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $fields['hash'] = hash_hmac('sha256', $checkString, $secretKey);

        return http_build_query($fields);
    }

    private function miniAppGet(string $uri, int $userId = 555111)
    {
        return $this->withHeaders(['X-Telegram-Init-Data' => $this->initDataFor($userId)])->getJson($uri);
    }

    private function miniAppPost(string $uri, array $data = [], int $userId = 555111)
    {
        return $this->withHeaders(['X-Telegram-Init-Data' => $this->initDataFor($userId)])->postJson($uri, $data);
    }

    public function test_requests_without_valid_init_data_are_rejected(): void
    {
        $this->getJson('/api/miniapp/customer/me')->assertStatus(401);
        $this->withHeaders(['X-Telegram-Init-Data' => 'garbage'])
            ->getJson('/api/miniapp/customer/me')->assertStatus(401);
    }

    public function test_me_auto_creates_the_customer_on_first_contact(): void
    {
        $this->miniAppGet('/api/miniapp/customer/me', 555111)
            ->assertOk()
            ->assertJsonPath('is_vip', false);

        $this->assertDatabaseHas('customers', ['telegram_chat_id' => '555111']);
    }

    public function test_mempool_returns_structured_stats(): void
    {
        Http::fake([
            'mempool.space/api/blocks/tip/height' => Http::response('850000'),
            'mempool.space/api/v1/fees/recommended' => Http::response([
                'fastestFee' => 20, 'halfHourFee' => 15, 'hourFee' => 10, 'economyFee' => 5, 'minimumFee' => 1,
            ]),
        ]);

        $this->miniAppGet('/api/miniapp/customer/mempool')
            ->assertOk()
            ->assertJsonPath('height', 850000)
            ->assertJsonPath('fees.fastestFee', 20);
    }

    public function test_alert_lifecycle(): void
    {
        $create = $this->miniAppPost('/api/miniapp/customer/alerts', [
            'currency' => 'PYG',
            'order_type' => 'BUY',
            'payment_methods' => ['PIX'],
        ])->assertStatus(201);

        $alertId = $create->json('id');
        $this->assertDatabaseHas('alerts', ['id' => $alertId, 'is_active' => true]);

        $this->withHeaders(['X-Telegram-Init-Data' => $this->initDataFor(555111)])
            ->patchJson("/api/miniapp/customer/alerts/{$alertId}/toggle")
            ->assertOk()
            ->assertJsonPath('is_active', false);

        $this->withHeaders(['X-Telegram-Init-Data' => $this->initDataFor(555111)])
            ->deleteJson("/api/miniapp/customer/alerts/{$alertId}")
            ->assertOk();

        $this->assertDatabaseMissing('alerts', ['id' => $alertId]);
    }

    public function test_customer_cannot_toggle_another_customers_alert(): void
    {
        $owner = Customer::factory()->create(['telegram_chat_id' => '999999']);
        $alert = Alert::factory()->create(['customer_id' => $owner->id]);

        $this->withHeaders(['X-Telegram-Init-Data' => $this->initDataFor(555111)])
            ->patchJson("/api/miniapp/customer/alerts/{$alert->id}/toggle")
            ->assertStatus(403);
    }

    public function test_escrow_job_lifecycle(): void
    {
        $this->mock(LnbitsClient::class, function ($mock) {
            $mock->shouldReceive('createInvoice')->once()
                ->andReturn(['payment_request' => 'lnbc1testinvoice', 'payment_hash' => 'hash123']);
        });

        $create = $this->miniAppPost('/api/miniapp/customer/escrow-jobs', [
            'amount_sats' => 5000,
            'description' => 'Traducción de documento',
        ])->assertStatus(201);

        $jobId = $create->json('id');

        $this->miniAppGet("/api/miniapp/customer/escrow-jobs/{$jobId}")
            ->assertOk()
            ->assertJsonPath('status', 'created');

        $this->miniAppGet('/api/miniapp/customer/escrow-jobs')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_create_escrow_job_returns_a_service_unavailable_error_when_lnbits_is_down(): void
    {
        $this->mock(LnbitsClient::class, fn ($mock) => $mock->shouldReceive('createInvoice')
            ->once()->andThrow(new RuntimeException('Failed to create invoice via LNbits.')));

        $this->miniAppPost('/api/miniapp/customer/escrow-jobs', [
            'amount_sats' => 5000,
            'description' => 'Traducción de documento',
        ])->assertStatus(503);

        $this->assertDatabaseCount('escrow_jobs', 0);
    }

    public function test_customer_cannot_view_another_customers_escrow_job(): void
    {
        $owner = Customer::factory()->create(['telegram_chat_id' => '999999']);
        $job = EscrowJob::factory()->create(['creator_customer_id' => $owner->id]);

        $this->miniAppGet("/api/miniapp/customer/escrow-jobs/{$job->id}")->assertStatus(403);
    }

    public function test_release_pays_out_and_updates_status(): void
    {
        $customer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['creator_customer_id' => $customer->id, 'status' => 'funded']);

        $this->mock(LnbitsClient::class, fn ($mock) => $mock->shouldReceive('payInvoice')->once()->andReturn(['payment_hash' => 'x']));

        $this->miniAppPost("/api/miniapp/customer/escrow-jobs/{$job->id}/release", ['payout_bolt11' => 'lnbc1payout'])
            ->assertOk()
            ->assertJsonPath('status', 'completed');
    }

    public function test_release_returns_a_service_unavailable_error_when_lnbits_is_down(): void
    {
        $customer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['creator_customer_id' => $customer->id, 'status' => 'funded']);

        $this->mock(LnbitsClient::class, fn ($mock) => $mock->shouldReceive('payInvoice')
            ->once()->andThrow(new RuntimeException('Failed to pay invoice via LNbits.')));

        $this->miniAppPost("/api/miniapp/customer/escrow-jobs/{$job->id}/release", ['payout_bolt11' => 'lnbc1payout'])
            ->assertStatus(503);

        $this->assertSame('funded', $job->fresh()->status);
    }

    public function test_dispute_opens_a_dispute(): void
    {
        $customer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['creator_customer_id' => $customer->id, 'status' => 'funded']);

        $this->miniAppPost("/api/miniapp/customer/escrow-jobs/{$job->id}/dispute", ['reason' => 'No entregó el trabajo'])
            ->assertOk()
            ->assertJsonPath('status', 'disputed');

        $this->assertDatabaseHas('escrow_disputes', ['escrow_job_id' => $job->id, 'status' => 'open']);
    }
}
