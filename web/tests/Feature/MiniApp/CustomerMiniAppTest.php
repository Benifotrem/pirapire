<?php

namespace Tests\Feature\MiniApp;

use App\Models\Alert;
use App\Models\Customer;
use App\Models\EscrowJob;
use App\Models\EscrowJobApplication;
use App\Services\Lightning\LnbitsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

    /** postJson() would serialize an UploadedFile into JSON text instead of uploading it — this sends a real multipart request. */
    private function miniAppPostMultipart(string $uri, array $data = [], int $userId = 555111)
    {
        return $this->withHeaders(['X-Telegram-Init-Data' => $this->initDataFor($userId)])->post($uri, $data);
    }

    private function miniAppGetRaw(string $uri, int $userId = 555111)
    {
        return $this->withHeaders(['X-Telegram-Init-Data' => $this->initDataFor($userId)])->get($uri);
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

    public function test_store_escrow_job_posts_an_open_job_without_generating_an_invoice(): void
    {
        $this->mock(LnbitsClient::class, fn ($mock) => $mock->shouldNotReceive('createInvoice'));

        $create = $this->miniAppPost('/api/miniapp/customer/escrow-jobs', [
            'amount_sats' => 5000,
            'description' => 'Traducción de documento',
        ])->assertStatus(201);

        $jobId = $create->json('id');
        $this->assertDatabaseHas('escrow_jobs', ['id' => $jobId, 'status' => 'open']);

        $this->miniAppGet("/api/miniapp/customer/escrow-jobs/{$jobId}")
            ->assertOk()
            ->assertJsonPath('status', 'open');

        $this->miniAppGet('/api/miniapp/customer/escrow-jobs')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_customer_cannot_view_another_customers_escrow_job(): void
    {
        $owner = Customer::factory()->create(['telegram_chat_id' => '999999']);
        $job = EscrowJob::factory()->create(['creator_customer_id' => $owner->id]);

        $this->miniAppGet("/api/miniapp/customer/escrow-jobs/{$job->id}")->assertStatus(403);
    }

    public function test_the_assigned_freelancer_can_also_view_the_job(): void
    {
        $freelancer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['status' => 'funded', 'counterparty_customer_id' => $freelancer->id]);

        $this->miniAppGet("/api/miniapp/customer/escrow-jobs/{$job->id}")
            ->assertOk()
            ->assertJsonPath('status', 'funded');
    }

    public function test_open_jobs_lists_others_postings_but_not_the_callers_own(): void
    {
        Customer::factory()->create(['telegram_chat_id' => '555111']);
        $someoneElse = Customer::factory()->create();
        EscrowJob::factory()->create(['status' => 'open', 'creator_customer_id' => $someoneElse->id, 'counterparty_customer_id' => null]);

        $this->miniAppPost('/api/miniapp/customer/escrow-jobs', ['amount_sats' => 1000, 'description' => 'Mi propio trabajo']);

        $this->miniAppGet('/api/miniapp/customer/escrow-jobs/open')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_cancel_open_job(): void
    {
        $customer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['status' => 'open', 'creator_customer_id' => $customer->id, 'counterparty_customer_id' => null]);

        $this->miniAppPost("/api/miniapp/customer/escrow-jobs/{$job->id}/cancel")
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');
    }

    public function test_cancel_open_job_rejects_a_customer_who_is_not_the_creator(): void
    {
        $owner = Customer::factory()->create(['telegram_chat_id' => '999999']);
        $job = EscrowJob::factory()->create(['status' => 'open', 'creator_customer_id' => $owner->id, 'counterparty_customer_id' => null]);

        $this->miniAppPost("/api/miniapp/customer/escrow-jobs/{$job->id}/cancel")->assertStatus(403);
    }

    public function test_apply_to_job_creates_a_pending_application(): void
    {
        Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['status' => 'open', 'counterparty_customer_id' => null]);

        $this->miniAppPost("/api/miniapp/customer/escrow-jobs/{$job->id}/apply", ['message' => 'Puedo empezar hoy'])
            ->assertStatus(201)
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('escrow_job_applications', ['escrow_job_id' => $job->id, 'message' => 'Puedo empezar hoy']);
    }

    public function test_job_applications_is_only_visible_to_the_jobs_creator(): void
    {
        $owner = Customer::factory()->create(['telegram_chat_id' => '999999']);
        $job = EscrowJob::factory()->create(['status' => 'open', 'creator_customer_id' => $owner->id, 'counterparty_customer_id' => null]);
        EscrowJobApplication::factory()->create(['escrow_job_id' => $job->id]);

        $this->miniAppGet("/api/miniapp/customer/escrow-jobs/{$job->id}/applications")->assertStatus(403);
    }

    public function test_accept_application_assigns_the_freelancer_and_generates_the_funding_invoice(): void
    {
        $customer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['status' => 'open', 'creator_customer_id' => $customer->id, 'counterparty_customer_id' => null]);
        $application = EscrowJobApplication::factory()->create(['escrow_job_id' => $job->id, 'status' => 'pending']);

        $this->mock(LnbitsClient::class, function ($mock) {
            $mock->shouldReceive('createInvoice')->once()
                ->andReturn(['payment_request' => 'lnbc1testinvoice', 'payment_hash' => 'hash123']);
        });

        $this->miniAppPost("/api/miniapp/customer/escrow-jobs/{$job->id}/applications/{$application->id}/accept")
            ->assertOk()
            ->assertJsonPath('status', 'assigned')
            ->assertJsonPath('funding_invoice', 'lnbc1testinvoice');

        $this->assertSame($application->fresh()->freelancer_customer_id, $job->fresh()->counterparty_customer_id);
    }

    public function test_accept_application_rejects_a_customer_who_is_not_the_creator(): void
    {
        $owner = Customer::factory()->create(['telegram_chat_id' => '999999']);
        $job = EscrowJob::factory()->create(['status' => 'open', 'creator_customer_id' => $owner->id, 'counterparty_customer_id' => null]);
        $application = EscrowJobApplication::factory()->create(['escrow_job_id' => $job->id, 'status' => 'pending']);

        $this->mock(LnbitsClient::class, fn ($mock) => $mock->shouldNotReceive('createInvoice'));

        $this->miniAppPost("/api/miniapp/customer/escrow-jobs/{$job->id}/applications/{$application->id}/accept")
            ->assertStatus(422);
    }

    public function test_deliver_stores_the_freelancers_payout_invoice(): void
    {
        $freelancer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['status' => 'funded', 'counterparty_customer_id' => $freelancer->id]);

        $this->miniAppPost("/api/miniapp/customer/escrow-jobs/{$job->id}/deliver", ['payout_bolt11' => 'lnbc1freelancerpayout'])
            ->assertOk()
            ->assertJsonPath('status', 'delivered');

        $this->assertSame('lnbc1freelancerpayout', $job->fresh()->freelancer_payout_invoice);
    }

    public function test_deliver_rejects_a_customer_who_is_not_the_assigned_freelancer(): void
    {
        Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['status' => 'funded']);

        $this->miniAppPost("/api/miniapp/customer/escrow-jobs/{$job->id}/deliver", ['payout_bolt11' => 'lnbc1stolenpayout'])
            ->assertStatus(403);
    }

    public function test_deliver_accepts_an_optional_proof_image(): void
    {
        Storage::fake('escrow-proofs');
        $freelancer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['status' => 'funded', 'counterparty_customer_id' => $freelancer->id]);

        $this->miniAppPostMultipart("/api/miniapp/customer/escrow-jobs/{$job->id}/deliver", [
            'payout_bolt11' => 'lnbc1freelancerpayout',
            'proof' => UploadedFile::fake()->image('screenshot.jpg'),
        ])->assertOk();

        $job->refresh();
        $this->assertNotNull($job->proof_path);
        Storage::disk('escrow-proofs')->assertExists($job->proof_path);
    }

    public function test_only_a_party_to_the_job_can_fetch_its_proof_image(): void
    {
        Storage::fake('escrow-proofs');
        $creator = Customer::factory()->create(['telegram_chat_id' => '111111']);
        $freelancer = Customer::factory()->create(['telegram_chat_id' => '222222']);
        $intruder = Customer::factory()->create(['telegram_chat_id' => '333333']);
        $proofPath = UploadedFile::fake()->image('screenshot.jpg')->store('proofs', 'escrow-proofs');
        $job = EscrowJob::factory()->create([
            'status' => 'delivered',
            'creator_customer_id' => $creator->id,
            'counterparty_customer_id' => $freelancer->id,
            'proof_path' => $proofPath,
        ]);

        $this->miniAppGetRaw("/api/miniapp/customer/escrow-jobs/{$job->id}/proof", 111111)->assertOk();
        $this->miniAppGetRaw("/api/miniapp/customer/escrow-jobs/{$job->id}/proof", 222222)->assertOk();
        $this->miniAppGetRaw("/api/miniapp/customer/escrow-jobs/{$job->id}/proof", 333333)->assertForbidden();
    }

    public function test_my_freelance_jobs_lists_jobs_assigned_to_the_caller(): void
    {
        $freelancer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        EscrowJob::factory()->create(['status' => 'funded', 'counterparty_customer_id' => $freelancer->id]);
        EscrowJob::factory()->create(); // someone else's assignment

        $this->miniAppGet('/api/miniapp/customer/escrow-jobs/mine-as-freelancer')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_release_pays_the_freelancers_stored_invoice_and_updates_status(): void
    {
        $customer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['creator_customer_id' => $customer->id, 'status' => 'delivered', 'freelancer_payout_invoice' => 'lnbc1freelancerpayout']);

        $this->mock(LnbitsClient::class, fn ($mock) => $mock->shouldReceive('payInvoice')->once()->with('lnbc1freelancerpayout')->andReturn(['payment_hash' => 'x']));

        $this->miniAppPost("/api/miniapp/customer/escrow-jobs/{$job->id}/release")
            ->assertOk()
            ->assertJsonPath('status', 'completed');
    }

    public function test_release_returns_a_service_unavailable_error_when_lnbits_is_down(): void
    {
        $customer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['creator_customer_id' => $customer->id, 'status' => 'delivered', 'freelancer_payout_invoice' => 'lnbc1freelancerpayout']);

        $this->mock(LnbitsClient::class, fn ($mock) => $mock->shouldReceive('payInvoice')
            ->once()->andThrow(new RuntimeException('Failed to pay invoice via LNbits.')));

        $this->miniAppPost("/api/miniapp/customer/escrow-jobs/{$job->id}/release")
            ->assertStatus(503);

        $this->assertSame('delivered', $job->fresh()->status);
    }

    public function test_release_rejects_a_customer_who_is_not_the_creator(): void
    {
        $owner = Customer::factory()->create(['telegram_chat_id' => '999999']);
        $job = EscrowJob::factory()->create(['creator_customer_id' => $owner->id, 'status' => 'delivered', 'freelancer_payout_invoice' => 'lnbc1legitpayout']);

        $this->mock(LnbitsClient::class, fn ($mock) => $mock->shouldNotReceive('payInvoice'));

        $this->miniAppPost("/api/miniapp/customer/escrow-jobs/{$job->id}/release")->assertStatus(403);
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

    public function test_dispute_may_also_be_opened_by_the_assigned_freelancer(): void
    {
        $freelancer = Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['status' => 'delivered', 'counterparty_customer_id' => $freelancer->id]);

        $this->miniAppPost("/api/miniapp/customer/escrow-jobs/{$job->id}/dispute", ['reason' => 'El cliente no libera el pago'])
            ->assertOk()
            ->assertJsonPath('status', 'disputed');
    }

    public function test_dispute_rejects_a_customer_who_is_not_a_party(): void
    {
        Customer::factory()->create(['telegram_chat_id' => '555111']);
        $job = EscrowJob::factory()->create(['status' => 'funded']);

        $this->miniAppPost("/api/miniapp/customer/escrow-jobs/{$job->id}/dispute", ['reason' => 'No tengo nada que ver'])
            ->assertStatus(403);
    }
}
