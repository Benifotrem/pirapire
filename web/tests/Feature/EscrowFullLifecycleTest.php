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
 * EscrowService's collaborators) — open -> assigned -> funded (via the real
 * LNbits webhook route) -> in_progress -> delivered -> completed, and
 * separately open -> ... -> funded -> disputed -> refunded. Complements the
 * collaborator-level mocks in tests/Unit/EscrowServiceTest.php.
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
        config(['services.escrow.fee_percent' => 1.5]);
        $client = Customer::factory()->create();
        $freelancer = Customer::factory()->create();
        $escrow = app(EscrowService::class);

        // 1. postJob — open, no invoice, no freelancer yet.
        $job = $escrow->postJob($client, 1000, 'Diseño de logo');
        $this->assertSame('open', $job->status);
        $this->assertSame(15, $job->fee_sats);
        $this->assertNull($job->payment_hash);

        // 2. applyToJob + acceptApplication — assigns the freelancer and
        // only now generates the funding invoice.
        $application = $escrow->applyToJob($job, $freelancer, 'Puedo empezar hoy');
        $job = $escrow->acceptApplication($application, $client);

        $this->assertSame('assigned', $job->status);
        $this->assertSame($freelancer->id, $job->counterparty_customer_id);
        $this->assertNotEmpty($job->payment_hash);

        // 3. markFunded, driven through the real LNbits webhook endpoint.
        $this->postJson('/api/escrow/webhook', ['payment_hash' => $job->payment_hash])
            ->assertOk();

        $this->assertSame('funded', $job->fresh()->status);
        $this->assertNotNull($job->fresh()->funded_at);

        // 4. markInProgress
        $escrow->markInProgress($job->fresh(), $freelancer);
        $this->assertSame('in_progress', $job->fresh()->status);

        // 5. deliver — the freelancer submits their own payout invoice.
        $escrow->deliver($job->fresh(), $freelancer, 'lnbc1freelancerpayout...');
        $job->refresh();
        $this->assertSame('delivered', $job->status);
        $this->assertSame('lnbc1freelancerpayout...', $job->freelancer_payout_invoice);

        // 6. release — pays the invoice the freelancer submitted at delivery.
        $escrow->release($job->fresh(), $client);

        $job->refresh();
        $this->assertSame('completed', $job->status);
        $this->assertNotNull($job->settled_at);
        $this->assertSame('lnbc1freelancerpayout...', $job->payout_destination);
    }

    public function test_dispute_and_refund_flow(): void
    {
        $this->fakeLnbitsWallet();
        $client = Customer::factory()->create();
        $freelancer = Customer::factory()->create();
        $escrow = app(EscrowService::class);

        $job = $escrow->postJob($client, 2000, 'Traducción de sitio web');
        $application = $escrow->applyToJob($job, $freelancer, 'Nativo en ambos idiomas');
        $job = $escrow->acceptApplication($application, $client);

        $this->postJson('/api/escrow/webhook', ['payment_hash' => $job->payment_hash])
            ->assertOk();

        // The freelancer never delivers — the client disputes from 'funded'.
        $dispute = $escrow->openDispute($job->fresh(), $client, 'El trabajo no fue entregado');

        $this->assertSame('disputed', $job->fresh()->status);
        $this->assertSame('open', $dispute->status);

        // Admin resolves by refunding — must also auto-resolve the open dispute.
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
        $job = EscrowJob::factory()->create([
            'status' => 'funded',
            'payment_hash' => 'already-funded-hash',
        ]);

        $this->postJson('/api/escrow/webhook', ['payment_hash' => 'already-funded-hash'])
            ->assertOk();

        $this->assertSame('funded', $job->fresh()->status);
    }

    public function test_open_job_has_no_payment_hash_until_a_freelancer_is_accepted(): void
    {
        $job = app(EscrowService::class)->postJob(Customer::factory()->create(), 1000, 'Aún sin freelancer');

        $this->assertNull($job->payment_hash);
    }
}
