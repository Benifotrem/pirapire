<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EscrowJob;
use App\Services\Lightning\LnbitsClient;
use App\Services\Telegram\CustomerTelegramBotClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramCustomerWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram_customer_bot.webhook_secret' => 'customer-secret']);
    }

    private function postUpdate(array $message, string $secret = 'customer-secret')
    {
        return $this->postJson('/api/telegram/customer-webhook', ['message' => $message], [
            'X-Telegram-Bot-Api-Secret-Token' => $secret,
        ]);
    }

    public function test_rejects_updates_with_the_wrong_secret(): void
    {
        $this->mock(CustomerTelegramBotClient::class, fn ($mock) => $mock->shouldNotReceive('sendMessage'));

        $this->postUpdate(['chat' => ['id' => 1], 'text' => '/start'], 'wrong')->assertStatus(401);
    }

    public function test_start_creates_a_customer_and_replies(): void
    {
        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()->with('555111', \Mockery::type('string'));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => '/start'])
            ->assertOk()
            ->assertJsonPath('status', 'replied');

        $this->assertDatabaseHas('customers', ['telegram_chat_id' => '555111']);
    }

    public function test_mempool_command_replies_with_fee_summary(): void
    {
        Http::fake([
            'mempool.space/api/blocks/tip/height' => Http::response('850000'),
            'mempool.space/api/v1/fees/recommended' => Http::response([
                'fastestFee' => 20, 'halfHourFee' => 15, 'hourFee' => 10, 'economyFee' => 5, 'minimumFee' => 1,
            ]),
        ]);

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, '850000')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => '/mempool'])->assertOk();
    }

    public function test_vip_command_reports_free_tier_by_default(): void
    {
        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, 'plan gratuito')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => '/vip'])->assertOk();
    }

    public function test_unrecognized_plain_text_gets_no_reply(): void
    {
        $this->mock(CustomerTelegramBotClient::class, fn ($mock) => $mock->shouldNotReceive('sendMessage'));

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => 'hola'])
            ->assertOk()
            ->assertJsonPath('status', 'ignored');
    }

    public function test_escrow_create_creates_a_job_and_replies_with_the_funding_invoice(): void
    {
        $this->mock(LnbitsClient::class, function ($mock) {
            $mock->shouldReceive('createInvoice')
                ->once()
                ->andReturn(['payment_request' => 'lnbc1testinvoice', 'payment_hash' => 'hash123']);
        });

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, 'lnbc1testinvoice')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => '/escrow create 5000 Traducción de documento'])
            ->assertOk();

        $this->assertDatabaseHas('escrow_jobs', ['amount_sats' => 5000, 'status' => 'created']);
    }

    public function test_escrow_status_reports_an_existing_job(): void
    {
        $customer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['creator_customer_id' => $customer->id, 'status' => 'funded']);

        $this->mock(CustomerTelegramBotClient::class, function ($mock) use ($job) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, $job->id) && str_contains($msg, 'funded')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => "/escrow status {$job->id}"])->assertOk();
    }

    public function test_escrow_release_pays_out_and_replies_with_success(): void
    {
        $customer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['creator_customer_id' => $customer->id, 'status' => 'funded']);

        $this->mock(LnbitsClient::class, function ($mock) {
            $mock->shouldReceive('payInvoice')->once()->andReturn(['payment_hash' => 'x']);
        });

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, 'liberados')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => "/escrow release {$job->id} lnbc1payout"])
            ->assertOk();

        $this->assertSame('completed', $job->fresh()->status);
    }

    public function test_escrow_dispute_opens_a_dispute(): void
    {
        $customer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['creator_customer_id' => $customer->id, 'status' => 'funded']);

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, 'Disputa abierta')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => "/escrow dispute {$job->id}"])->assertOk();

        $this->assertSame('disputed', $job->fresh()->status);
        $this->assertDatabaseHas('escrow_disputes', ['escrow_job_id' => $job->id, 'status' => 'open']);
    }
}
