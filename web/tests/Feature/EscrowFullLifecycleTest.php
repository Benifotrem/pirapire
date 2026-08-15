<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EscrowJob;
use App\Services\Escrow\EscrowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Drives the full escrow state machine end to end against a fake LNbits
 * wallet (an Http::fake() double of the real payments API, not a mock of
 * EscrowService's collaborators) — created -> funded (via the real LNbits
 * webhook route) -> in_progress -> completed, and separately
 * created -> funded -> disputed -> refunded. Complements the collaborator-
 * level mocks in tests/Unit/EscrowServiceTest.php.
 */
class EscrowFullLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function fakeLnbitsWallet(): void
    {
        Http::fake(function ($request) {
            if ($request->url() !== 'http://lnbits.test/api/v1/payments') {
                return Http::response(['message' => 'unexpected request'], 404);
            }

            if ($request['out'] ?? false) {
                return Http::response([
                    'payment_hash' => 'payout-'.substr(md5($request['bolt11']), 0, 12),
                ], 201);
            }

            return Http::response([
                'payment_hash' => 'fund-'.substr(md5($request['memo']), 0, 12),
                'payment_request' => 'lnbc1fundinginvoice...',
            ], 201);
        });

        config(['services.lnbits.base_url' => 'http://lnbits.test']);
    }

    public function test_full_happy_path_release_flow(): void
    {
        $this->fakeLnbitsWallet();
        $customer = Customer::factory()->create();

        // 1. createJob
        $job = app(EscrowService::class)
            ->createJob($customer, 1000, 'Diseño de logo');

        $this->assertSame('created', $job->status);
        $this->assertSame(15, $job->fee_sats);
        $this->assertNotEmpty($job->payment_hash);

        // 2. markFunded, driven through the real LNbits webhook endpoint.
        $this->postJson('/api/escrow/webhook', ['payment_hash' => $job->payment_hash])
            ->assertOk();

        $this->assertSame('funded', $job->fresh()->status);
        $this->assertNotNull($job->fresh()->funded_at);

        // 3. startJob (markInProgress)
        app(EscrowService::class)->markInProgress($job->fresh());

        $this->assertSame('in_progress', $job->fresh()->status);

        // 4. release
        app(EscrowService::class)
            ->release($job->fresh(), 'lnbc1payoutinvoice...');

        $job->refresh();
        $this->assertSame('completed', $job->status);
        $this->assertNotNull($job->settled_at);
        $this->assertSame('lnbc1payoutinvoice...', $job->payout_destination);
    }

    public function test_dispute_and_refund_flow(): void
    {
        $this->fakeLnbitsWallet();
        $customer = Customer::factory()->create();
        $escrow = app(EscrowService::class);

        $job = $escrow->createJob($customer, 2000, 'Traducción de sitio web');

        $this->postJson('/api/escrow/webhook', ['payment_hash' => $job->payment_hash])
            ->assertOk();

        // 5. openDispute
        $dispute = $escrow->openDispute($job->fresh(), $customer, 'El trabajo no fue entregado');

        $this->assertSame('disputed', $job->fresh()->status);
        $this->assertSame('open', $dispute->status);

        // 5. refund, which must also auto-resolve the open dispute.
        $escrow->refund($job->fresh(), 'lnbc1refundinvoice...');

        $job->refresh();
        $dispute->refresh();
        $this->assertSame('refunded', $job->status);
        $this->assertSame('resolved', $dispute->status);
        $this->assertSame('refunded_to_client', $dispute->resolution);
    }

    public function test_webhook_rejects_unknown_payment_hash(): void
    {
        $this->fakeLnbitsWallet();

        $this->postJson('/api/escrow/webhook', ['payment_hash' => 'does-not-exist'])
            ->assertNotFound();

        $this->assertDatabaseCount('escrow_jobs', 0);
    }

    public function test_webhook_is_idempotent_once_already_funded(): void
    {
        $this->fakeLnbitsWallet();
        $customer = Customer::factory()->create();
        $job = EscrowJob::factory()->create([
            'creator_customer_id' => $customer->id,
            'status' => 'funded',
            'payment_hash' => 'already-funded-hash',
        ]);

        $this->postJson('/api/escrow/webhook', ['payment_hash' => 'already-funded-hash'])
            ->assertOk();

        $this->assertSame('funded', $job->fresh()->status);
    }
}
