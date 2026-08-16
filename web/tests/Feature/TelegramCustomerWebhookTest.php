<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EscrowJob;
use App\Models\EscrowJobApplication;
use App\Services\Bot\CustomerCommandRouter;
use App\Services\Lightning\LnbitsClient;
use App\Services\Telegram\CustomerTelegramBotClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
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

    public function test_escrow_create_publishes_an_open_job_without_an_invoice(): void
    {
        $this->mock(LnbitsClient::class, fn ($mock) => $mock->shouldNotReceive('createInvoice'));

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, 'publicado') && str_contains($msg, 'applications')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => '/escrow create 5000 Traducción de documento'])
            ->assertOk();

        $this->assertDatabaseHas('escrow_jobs', ['amount_sats' => 5000, 'status' => 'open']);
    }

    public function test_escrow_browse_lists_open_jobs_from_other_customers(): void
    {
        Customer::factory()->create(['telegram_chat_id' => '555111']);
        $other = Customer::factory()->create();
        EscrowJob::factory()->create(['status' => 'open', 'creator_customer_id' => $other->id, 'counterparty_customer_id' => null, 'description' => 'Traducir un sitio web']);

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, 'Traducir un sitio web')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => '/escrow browse'])->assertOk();
    }

    public function test_escrow_browse_excludes_the_callers_own_open_jobs(): void
    {
        $customer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        EscrowJob::factory()->create(['status' => 'open', 'creator_customer_id' => $customer->id, 'counterparty_customer_id' => null]);

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', 'No hay trabajos abiertos en este momento.');
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => '/escrow browse'])->assertOk();
    }

    public function test_escrow_apply_creates_a_pending_application(): void
    {
        Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['status' => 'open', 'counterparty_customer_id' => null]);

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, 'Te postulaste')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => "/escrow apply {$job->id} Puedo hacerlo hoy mismo"])->assertOk();

        $this->assertDatabaseHas('escrow_job_applications', [
            'escrow_job_id' => $job->id,
            'message' => 'Puedo hacerlo hoy mismo',
            'status' => 'pending',
        ]);
    }

    public function test_escrow_applications_lists_pending_applications_on_the_callers_job(): void
    {
        $customer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['status' => 'open', 'creator_customer_id' => $customer->id, 'counterparty_customer_id' => null]);
        $freelancer = Customer::factory()->create(['display_name' => 'Ana']);
        EscrowJobApplication::factory()->create(['escrow_job_id' => $job->id, 'freelancer_customer_id' => $freelancer->id, 'message' => 'Soy la indicada', 'status' => 'pending']);

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, 'Ana') && str_contains($msg, 'Soy la indicada')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => "/escrow applications {$job->id}"])->assertOk();
    }

    public function test_escrow_applications_on_someone_elses_job_is_rejected(): void
    {
        Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['status' => 'open', 'counterparty_customer_id' => null]);

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, 'No se encontró')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => "/escrow applications {$job->id}"])->assertOk();
    }

    public function test_escrow_accept_assigns_the_freelancer_and_replies_with_the_funding_invoice(): void
    {
        $customer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['status' => 'open', 'creator_customer_id' => $customer->id, 'counterparty_customer_id' => null]);
        $application = EscrowJobApplication::factory()->create(['escrow_job_id' => $job->id, 'status' => 'pending']);

        $this->mock(LnbitsClient::class, function ($mock) {
            $mock->shouldReceive('createInvoice')->once()
                ->andReturn(['payment_request' => 'lnbc1fundinginvoice', 'payment_hash' => 'hash123']);
        });

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, 'lnbc1fundinginvoice')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => "/escrow accept {$job->id} {$application->id}"])->assertOk();

        $job->refresh();
        $this->assertSame('assigned', $job->status);
        $this->assertSame($application->fresh()->freelancer_customer_id, $job->counterparty_customer_id);
    }

    public function test_escrow_accept_on_someone_elses_job_is_rejected(): void
    {
        Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['status' => 'open', 'counterparty_customer_id' => null]);
        $application = EscrowJobApplication::factory()->create(['escrow_job_id' => $job->id, 'status' => 'pending']);

        $this->mock(LnbitsClient::class, fn ($mock) => $mock->shouldNotReceive('createInvoice'));

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, 'No se encontró')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => "/escrow accept {$job->id} {$application->id}"])->assertOk();

        $this->assertSame('open', $job->fresh()->status);
    }

    public function test_escrow_deliver_stores_the_freelancers_invoice(): void
    {
        $freelancer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['status' => 'funded', 'counterparty_customer_id' => $freelancer->id]);

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, 'entregado')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => "/escrow deliver {$job->id} lnbc1freelancerpayout"])->assertOk();

        $job->refresh();
        $this->assertSame('delivered', $job->status);
        $this->assertSame('lnbc1freelancerpayout', $job->freelancer_payout_invoice);
    }

    public function test_escrow_deliver_by_a_non_assigned_customer_is_rejected(): void
    {
        Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['status' => 'funded']);

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, 'No se encontró')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => "/escrow deliver {$job->id} lnbc1stolenpayout"])->assertOk();

        $this->assertSame('funded', $job->fresh()->status);
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

    public function test_escrow_status_is_also_visible_to_the_assigned_freelancer(): void
    {
        $freelancer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['status' => 'funded', 'counterparty_customer_id' => $freelancer->id]);

        $this->mock(CustomerTelegramBotClient::class, function ($mock) use ($job) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, $job->id)));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => "/escrow status {$job->id}"])->assertOk();
    }

    public function test_escrow_release_pays_the_freelancers_stored_invoice_and_replies_with_success(): void
    {
        $customer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['creator_customer_id' => $customer->id, 'status' => 'delivered', 'freelancer_payout_invoice' => 'lnbc1freelancerpayout']);

        $this->mock(LnbitsClient::class, function ($mock) {
            $mock->shouldReceive('payInvoice')->once()->with('lnbc1freelancerpayout')->andReturn(['payment_hash' => 'x']);
        });

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, 'liberados')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => "/escrow release {$job->id}"])
            ->assertOk();

        $this->assertSame('completed', $job->fresh()->status);
    }

    public function test_escrow_status_on_someone_elses_job_is_rejected(): void
    {
        $owner = Customer::factory()->create(['telegram_chat_id' => '555222']);
        $job = EscrowJob::factory()->create(['creator_customer_id' => $owner->id, 'status' => 'funded']);
        Customer::factory()->create(['telegram_chat_id' => '555111']); // the one issuing the command

        $this->mock(CustomerTelegramBotClient::class, function ($mock) use ($job) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, (string) $job->id) && str_contains($msg, 'No se encontró')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => "/escrow status {$job->id}"])->assertOk();
    }

    public function test_escrow_release_on_someone_elses_job_is_rejected(): void
    {
        $owner = Customer::factory()->create(['telegram_chat_id' => '555222']);
        $job = EscrowJob::factory()->create(['creator_customer_id' => $owner->id, 'status' => 'delivered', 'freelancer_payout_invoice' => 'lnbc1legitpayout']);
        Customer::factory()->create(['telegram_chat_id' => '555111']); // the one issuing the command

        $this->mock(LnbitsClient::class, fn ($mock) => $mock->shouldNotReceive('payInvoice'));

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, 'No se encontró')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => "/escrow release {$job->id}"])
            ->assertOk();

        $this->assertSame('delivered', $job->fresh()->status);
    }

    public function test_escrow_dispute_on_someone_elses_job_is_rejected(): void
    {
        $owner = Customer::factory()->create(['telegram_chat_id' => '555222']);
        $job = EscrowJob::factory()->create(['creator_customer_id' => $owner->id, 'status' => 'funded']);
        Customer::factory()->create(['telegram_chat_id' => '555111']); // the one issuing the command

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, 'No se encontró')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => "/escrow dispute {$job->id}"])->assertOk();

        $this->assertSame('funded', $job->fresh()->status);
        $this->assertDatabaseCount('escrow_disputes', 0);
    }

    public function test_escrow_dispute_may_also_be_opened_by_the_assigned_freelancer(): void
    {
        $freelancer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['status' => 'delivered', 'counterparty_customer_id' => $freelancer->id]);

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, 'Disputa abierta')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => "/escrow dispute {$job->id} el cliente no libera el pago"])->assertOk();

        $this->assertSame('disputed', $job->fresh()->status);
        $this->assertDatabaseHas('escrow_disputes', [
            'escrow_job_id' => $job->id,
            'opened_by_customer_id' => $freelancer->id,
            'reason' => 'el cliente no libera el pago',
        ]);
    }

    public function test_escrow_cancel_cancels_an_open_job(): void
    {
        $customer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['status' => 'open', 'creator_customer_id' => $customer->id, 'counterparty_customer_id' => null]);

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, 'cancelado')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => "/escrow cancel {$job->id}"])->assertOk();

        $this->assertSame('cancelled', $job->fresh()->status);
    }

    public function test_missing_message_entirely_is_ignored_without_error(): void
    {
        $this->mock(CustomerTelegramBotClient::class, fn ($mock) => $mock->shouldNotReceive('sendMessage'));

        $this->postJson('/api/telegram/customer-webhook', ['update_id' => 1], [
            'X-Telegram-Bot-Api-Secret-Token' => 'customer-secret',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ignored');

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_chat_id_present_but_text_missing_is_ignored(): void
    {
        $this->mock(CustomerTelegramBotClient::class, fn ($mock) => $mock->shouldNotReceive('sendMessage'));

        $this->postJson('/api/telegram/customer-webhook', ['message' => ['chat' => ['id' => 555111]]], [
            'X-Telegram-Bot-Api-Secret-Token' => 'customer-secret',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'ignored');
    }

    public function test_escrow_accept_replies_gracefully_when_lnbits_is_unreachable(): void
    {
        $customer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['status' => 'open', 'creator_customer_id' => $customer->id, 'counterparty_customer_id' => null]);
        $application = EscrowJobApplication::factory()->create(['escrow_job_id' => $job->id, 'status' => 'pending']);

        $this->mock(LnbitsClient::class, function ($mock) {
            $mock->shouldReceive('createInvoice')->once()->andThrow(new RuntimeException('Failed to create invoice via LNbits.'));
        });

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, 'no está disponible')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => "/escrow accept {$job->id} {$application->id}"])
            ->assertOk()
            ->assertJsonPath('status', 'replied');

        $this->assertSame('open', $job->fresh()->status);
    }

    public function test_escrow_release_replies_gracefully_when_lnbits_is_unreachable(): void
    {
        $customer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['creator_customer_id' => $customer->id, 'status' => 'delivered', 'freelancer_payout_invoice' => 'lnbc1freelancerpayout']);

        $this->mock(LnbitsClient::class, function ($mock) {
            $mock->shouldReceive('payInvoice')->once()->andThrow(new RuntimeException('Failed to pay invoice via LNbits.'));
        });

        $this->mock(CustomerTelegramBotClient::class, function ($mock) {
            $mock->shouldReceive('sendMessage')->once()
                ->with('555111', \Mockery::on(fn ($msg) => str_contains($msg, 'no está disponible')));
        });

        $this->postUpdate(['chat' => ['id' => 555111], 'text' => "/escrow release {$job->id}"])
            ->assertOk();

        $this->assertSame('delivered', $job->fresh()->status);
    }

    public function test_unexpected_router_failure_never_returns_a_500(): void
    {
        $this->mock(CustomerCommandRouter::class, function ($mock) {
            $mock->shouldReceive('route')->once()->andThrow(new RuntimeException('boom', 0));
        });

        // A RuntimeException from the router itself (as opposed to one already
        // caught and translated inside it) still must not surface as a 500 —
        // it's swallowed by the same catch as a failed sendMessage() call.
        $this->postUpdate(['chat' => ['id' => 555111], 'text' => '/mempool'])
            ->assertOk();
    }
}
