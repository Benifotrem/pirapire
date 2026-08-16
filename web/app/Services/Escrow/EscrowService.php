<?php

namespace App\Services\Escrow;

use App\Models\Customer;
use App\Models\EscrowDispute;
use App\Models\EscrowJob;
use App\Models\EscrowJobApplication;
use App\Services\Lightning\LnbitsClient;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * State machine for Lightning job escrow — hiring, payment, and disputes
 * all happen on Pirapire itself, not coordinated off-platform:
 *
 *   open -> assigned -> funded -> in_progress -> delivered -> completed
 *                                       \             |          ^
 *                                        \            v          |
 *                                         `------> disputed ------`
 *                                                      |
 *                                                      v
 *                                                  refunded
 *   open -> cancelled (creator cancels before picking a freelancer)
 *   assigned -> cancelled (never funded, expired)
 *
 * `open`: the creator posted a job (amount + description); freelancers can
 * see it and apply (EscrowJobApplication). `assigned`: the creator accepted
 * one application — a funding invoice is generated at that point, not when
 * the job was first posted, since there's nothing to fund until someone is
 * actually going to do the work. `delivered`: the freelancer marked the job
 * done and submitted their own payout invoice — the creator's release()
 * pays that stored invoice, so a bolt11 never has to be relayed through an
 * outside chat. See LnbitsClient for why funding uses a regular invoice
 * rather than a real hold invoice (LNbits has no such extension).
 *
 * Authorization for who may call an action lives here, not just in the
 * Telegram bot router / Mini App controllers that call it — see the "not
 * checked here" write-up that led to a real IDOR (a customer could release
 * or dispute someone else's job by guessing its id) for why relying only on
 * the caller to remember an ownership check isn't enough.
 */
class EscrowService
{
    private const DEFAULT_EXPIRY_SECONDS = 3600;

    public function __construct(private readonly LnbitsClient $lnbits) {}

    public function feePercent(): float
    {
        return (float) config('services.escrow.fee_percent', 1.5);
    }

    public function calculateFee(int $amountSats): int
    {
        return (int) round($amountSats * $this->feePercent() / 100);
    }

    /** Posts an open job — no invoice yet, nothing to fund until a freelancer is picked. */
    public function postJob(Customer $creator, int $amountSats, string $description): EscrowJob
    {
        if ($amountSats <= 0) {
            throw new DomainException('El monto del escrow debe ser mayor a cero.');
        }

        return EscrowJob::create([
            'creator_customer_id' => $creator->id,
            'description' => $description,
            'amount_sats' => $amountSats,
            'fee_sats' => $this->calculateFee($amountSats),
            'status' => 'open',
        ]);
    }

    /** A freelancer pitches themselves for an open job. Re-applying updates the existing pitch. */
    public function applyToJob(EscrowJob $job, Customer $freelancer, string $message): EscrowJobApplication
    {
        $this->assertStatus($job, ['open']);

        if ($job->creator_customer_id === $freelancer->id) {
            throw new DomainException('No podés postularte a tu propio trabajo.');
        }

        return EscrowJobApplication::updateOrCreate(
            ['escrow_job_id' => $job->id, 'freelancer_customer_id' => $freelancer->id],
            ['message' => $message, 'status' => 'pending'],
        );
    }

    /**
     * The creator accepts one application: assigns that freelancer and
     * generates the funding invoice. Every other pending application on the
     * job is rejected in the same transaction.
     */
    public function acceptApplication(EscrowJobApplication $application, Customer $actingCreator): EscrowJob
    {
        $job = $application->job;
        $this->assertCreator($job, $actingCreator);
        $this->assertStatus($job, ['open']);

        if ($application->status !== 'pending') {
            throw new DomainException('Esa postulación ya no está disponible.');
        }

        $invoice = $this->lnbits->createInvoice(
            amountSats: $job->amount_sats + $job->fee_sats,
            memo: "Pirapire escrow: {$job->description}",
            webhookUrl: route('api.escrow.webhook'),
        );

        return DB::transaction(function () use ($job, $application, $invoice) {
            $job->update([
                'counterparty_customer_id' => $application->freelancer_customer_id,
                'status' => 'assigned',
                'funding_invoice' => $invoice['payment_request'] ?? $invoice['bolt11'] ?? '',
                'payment_hash' => $invoice['payment_hash'],
                'expires_at' => now()->addSeconds(self::DEFAULT_EXPIRY_SECONDS),
            ]);

            $application->update(['status' => 'accepted']);

            $job->applications()
                ->where('id', '!=', $application->id)
                ->where('status', 'pending')
                ->update(['status' => 'rejected']);

            return $job->fresh();
        });
    }

    /** Called from the LNbits webhook once the funding invoice is paid. */
    public function markFunded(EscrowJob $job): void
    {
        $this->assertStatus($job, ['assigned']);

        $job->update(['status' => 'funded', 'funded_at' => now()]);
    }

    public function markInProgress(EscrowJob $job, Customer $actingFreelancer): void
    {
        $this->assertFreelancer($job, $actingFreelancer);
        $this->assertStatus($job, ['funded']);

        $job->update(['status' => 'in_progress']);
    }

    /** The freelancer marks the job done and submits their own invoice to be paid out. */
    public function deliver(EscrowJob $job, Customer $actingFreelancer, string $payoutBolt11): void
    {
        $this->assertFreelancer($job, $actingFreelancer);
        $this->assertStatus($job, ['funded', 'in_progress']);

        $job->update(['status' => 'delivered', 'freelancer_payout_invoice' => $payoutBolt11]);
    }

    /**
     * Releases funds to the freelancer by paying the invoice they submitted
     * at delivery. $payoutBolt11 is an admin-only override for resolving a
     * dispute where the stored invoice expired — the customer-facing path
     * never passes it.
     */
    public function release(EscrowJob $job, ?Customer $actingCreator = null, ?string $payoutBolt11 = null): void
    {
        if ($actingCreator) {
            $this->assertCreator($job, $actingCreator);
        }
        $this->assertStatus($job, ['delivered', 'disputed']);

        $invoice = $payoutBolt11 ?? $job->freelancer_payout_invoice;
        if (! $invoice) {
            throw new DomainException('Todavía no hay una factura del freelancer para liberar este trabajo.');
        }

        DB::transaction(function () use ($job, $invoice) {
            $this->lnbits->payInvoice($invoice);

            $job->update([
                'status' => 'completed',
                'settled_at' => now(),
                'payout_destination' => $invoice,
            ]);

            // No-op via the `where('status', 'open')` clause when the job
            // wasn't disputed — matches refund()'s unconditional pattern.
            // (Checking $job->status here, after the update() above already
            // overwrote it to 'completed', would always be false.)
            $job->disputes()->where('status', 'open')->update([
                'status' => 'resolved',
                'resolution' => 'released_to_freelancer',
                'resolved_at' => now(),
            ]);
        });
    }

    /** Refunds the client in full (amount + fee). Admin-only — no customer-facing path calls this. */
    public function refund(EscrowJob $job, string $refundBolt11): void
    {
        $this->assertStatus($job, ['funded', 'in_progress', 'delivered', 'disputed']);

        DB::transaction(function () use ($job, $refundBolt11) {
            $this->lnbits->payInvoice($refundBolt11);

            $job->update([
                'status' => 'refunded',
                'payout_destination' => $refundBolt11,
            ]);

            $job->disputes()->where('status', 'open')->update([
                'status' => 'resolved',
                'resolution' => 'refunded_to_client',
                'resolved_at' => now(),
            ]);
        });
    }

    public function cancelOpenJob(EscrowJob $job, Customer $actingCreator): void
    {
        $this->assertCreator($job, $actingCreator);
        $this->assertStatus($job, ['open']);

        $job->update(['status' => 'cancelled']);
    }

    /** Cancels a job that was assigned to a freelancer but never funded before its invoice expired. */
    public function cancelUnfundedAssignment(EscrowJob $job): void
    {
        $this->assertStatus($job, ['assigned']);

        $job->update(['status' => 'cancelled']);
    }

    public function openDispute(EscrowJob $job, Customer $openedBy, string $reason): EscrowDispute
    {
        if (! in_array($openedBy->id, [$job->creator_customer_id, $job->counterparty_customer_id], true)) {
            throw new DomainException('Solo el cliente o el freelancer de este trabajo pueden abrir una disputa.');
        }
        $this->assertStatus($job, ['funded', 'in_progress', 'delivered']);

        return DB::transaction(function () use ($job, $openedBy, $reason) {
            $job->update(['status' => 'disputed']);

            return $job->disputes()->create([
                'opened_by_customer_id' => $openedBy->id,
                'reason' => $reason,
                'status' => 'open',
            ]);
        });
    }

    private function assertCreator(EscrowJob $job, Customer $customer): void
    {
        if ($job->creator_customer_id !== $customer->id) {
            throw new DomainException('No encontrado.');
        }
    }

    private function assertFreelancer(EscrowJob $job, Customer $customer): void
    {
        if ($job->counterparty_customer_id !== $customer->id) {
            throw new DomainException('No encontrado.');
        }
    }

    private function assertStatus(EscrowJob $job, array $allowed): void
    {
        if (! in_array($job->status, $allowed, true)) {
            throw new DomainException(
                "Transición inválida: el escrow {$job->id} está en estado '{$job->status}'.",
            );
        }
    }
}
