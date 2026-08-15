<?php

namespace App\Services\Escrow;

use App\Models\Customer;
use App\Models\EscrowDispute;
use App\Models\EscrowJob;
use App\Services\Lightning\LnbitsClient;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * State machine for Lightning job escrow:
 *
 *   created -> funded -> in_progress -> completed
 *                    \-> disputed -> completed | refunded
 *   created -> cancelled (expired / never funded)
 *
 * LNbits has no "hold invoice" extension (see LnbitsClient), so this
 * isn't a true HTLC-hold escrow — the client's funding invoice settles
 * into the platform's LNbits wallet immediately, and the platform holds
 * that balance for the life of the job. "Releasing" or "refunding" means
 * actively paying out a fresh invoice supplied by the freelancer/client
 * at that moment (Lightning invoices expire, so there's nothing to store
 * upfront). The platform takes a fee (config('services.escrow.fee_percent'),
 * default 1.5%) on top of the job amount, charged in the funding invoice;
 * release only pays out amount_sats, so the fee stays in the wallet.
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

    public function createJob(Customer $creator, int $amountSats, string $description): EscrowJob
    {
        if ($amountSats <= 0) {
            throw new DomainException('El monto del escrow debe ser mayor a cero.');
        }

        $feeSats = $this->calculateFee($amountSats);

        $invoice = $this->lnbits->createInvoice(
            amountSats: $amountSats + $feeSats,
            memo: "Pirapire escrow: {$description}",
            webhookUrl: route('api.escrow.webhook'),
        );

        return EscrowJob::create([
            'creator_customer_id' => $creator->id,
            'description' => $description,
            'amount_sats' => $amountSats,
            'fee_sats' => $feeSats,
            'status' => 'created',
            'funding_invoice' => $invoice['payment_request'] ?? $invoice['bolt11'] ?? '',
            'payment_hash' => $invoice['payment_hash'],
            'expires_at' => now()->addSeconds(self::DEFAULT_EXPIRY_SECONDS),
        ]);
    }

    /** Called from the LNbits webhook once the funding invoice is paid. */
    public function markFunded(EscrowJob $job): void
    {
        $this->assertStatus($job, ['created']);

        $job->update(['status' => 'funded', 'funded_at' => now()]);
    }

    public function markInProgress(EscrowJob $job): void
    {
        $this->assertStatus($job, ['funded']);

        $job->update(['status' => 'in_progress']);
    }

    /** Releases funds to the freelancer by paying the bolt11 invoice they supply. */
    public function release(EscrowJob $job, string $payoutBolt11): void
    {
        $this->assertStatus($job, ['funded', 'in_progress', 'disputed']);

        DB::transaction(function () use ($job, $payoutBolt11) {
            $this->lnbits->payInvoice($payoutBolt11);

            $job->update([
                'status' => 'completed',
                'settled_at' => now(),
                'payout_destination' => $payoutBolt11,
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

    /** Refunds the client in full (amount + fee) by paying the bolt11 invoice they supply. */
    public function refund(EscrowJob $job, string $refundBolt11): void
    {
        $this->assertStatus($job, ['funded', 'in_progress', 'disputed']);

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

    public function cancelUnfunded(EscrowJob $job): void
    {
        $this->assertStatus($job, ['created']);

        $job->update(['status' => 'cancelled']);
    }

    public function openDispute(EscrowJob $job, Customer $openedBy, string $reason): EscrowDispute
    {
        $this->assertStatus($job, ['funded', 'in_progress']);

        return DB::transaction(function () use ($job, $openedBy, $reason) {
            $job->update(['status' => 'disputed']);

            return $job->disputes()->create([
                'opened_by_customer_id' => $openedBy->id,
                'reason' => $reason,
                'status' => 'open',
            ]);
        });
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
