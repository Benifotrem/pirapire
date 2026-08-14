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
 * The platform takes a fee (config('services.escrow.fee_percent'), default
 * 1.5%) on top of the job amount; the hold invoice charges amount+fee so
 * the freelancer receives the full job amount on release.
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
        $preimage = bin2hex(random_bytes(32));
        $paymentHash = hash('sha256', hex2bin($preimage));

        $invoice = $this->lnbits->createHoldInvoice(
            amountSats: $amountSats + $feeSats,
            paymentHash: $paymentHash,
            memo: "Pirapire escrow: {$description}",
            expirySeconds: self::DEFAULT_EXPIRY_SECONDS,
        );

        return EscrowJob::create([
            'creator_customer_id' => $creator->id,
            'description' => $description,
            'amount_sats' => $amountSats,
            'fee_sats' => $feeSats,
            'status' => 'created',
            'hold_invoice' => $invoice['payment_request'] ?? $invoice['bolt11'] ?? '',
            'payment_hash' => $paymentHash,
            'preimage' => $preimage,
            'expires_at' => now()->addSeconds(self::DEFAULT_EXPIRY_SECONDS),
        ]);
    }

    /** Called from the LNbits webhook once the hold invoice's HTLC is accepted (paid but not settled). */
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

    /** Releases funds to the freelancer by revealing the preimage (settling the hold invoice). */
    public function release(EscrowJob $job): void
    {
        $this->assertStatus($job, ['funded', 'in_progress', 'disputed']);

        DB::transaction(function () use ($job) {
            $settled = $this->lnbits->settleHoldInvoice($job->payment_hash, $job->preimage);

            if (! $settled) {
                throw new DomainException('No se pudo liquidar la hold invoice en LNbits.');
            }

            $job->update(['status' => 'completed', 'settled_at' => now()]);

            if ($job->status === 'disputed') {
                $job->disputes()->where('status', 'open')->update([
                    'status' => 'resolved',
                    'resolution' => 'released_to_freelancer',
                    'resolved_at' => now(),
                ]);
            }
        });
    }

    /** Cancels the hold invoice, rolling back the HTLC so the payer is refunded. */
    public function refund(EscrowJob $job): void
    {
        $this->assertStatus($job, ['created', 'funded', 'in_progress', 'disputed']);

        DB::transaction(function () use ($job) {
            $cancelled = $this->lnbits->cancelHoldInvoice($job->payment_hash);

            if (! $cancelled) {
                throw new DomainException('No se pudo cancelar la hold invoice en LNbits.');
            }

            $job->update(['status' => 'refunded']);

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
