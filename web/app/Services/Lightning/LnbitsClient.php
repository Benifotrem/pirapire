<?php

namespace App\Services\Lightning;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin wrapper around a self-hosted LNbits instance's "Hold Invoice"
 * extension REST API, used by App\Services\Escrow\EscrowService to fund,
 * settle, and cancel escrow hold invoices.
 *
 * A hold invoice is accepted (HTLC locked) when the payer pays it, but the
 * funds only move when the app later reveals the payment preimage
 * (settle) — or the invoice is cancelled and the HTLC is rolled back
 * (refund). This is what lets an escrow job hold BTC without custody of
 * a private key: LNbits/LND hold the HTLC, the app holds the preimage.
 */
class LnbitsClient
{
    private readonly string $baseUrl;

    public function __construct(
        ?string $baseUrl = null,
        private readonly ?string $adminKey = null,
        private readonly ?string $invoiceReadKey = null,
        private readonly ?string $extensionPath = null,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? config('services.lnbits.base_url'), '/');
        $this->adminKey ??= config('services.lnbits.admin_key');
        $this->invoiceReadKey ??= config('services.lnbits.invoice_read_key');
        $this->extensionPath ??= config('services.lnbits.hold_extension_path');
    }

    private function url(string $path): string
    {
        return $this->baseUrl.$this->extensionPath.$path;
    }

    /**
     * Creates a hold invoice for the given payment_hash/preimage pair.
     * The preimage must be generated and stored by the caller (EscrowService)
     * *before* calling this, since LNbits' hold-invoice endpoint accepts a
     * caller-supplied payment hash rather than generating one itself.
     */
    public function createHoldInvoice(
        int $amountSats,
        string $paymentHash,
        string $memo,
        int $expirySeconds,
    ): array {
        $response = Http::withHeaders(['X-Api-Key' => $this->adminKey])
            ->post($this->url('/invoice'), [
                'amount' => $amountSats,
                'payment_hash' => $paymentHash,
                'memo' => $memo,
                'expiry' => $expirySeconds,
                'webhook' => route('api.escrow.webhook'),
            ]);

        if ($response->failed()) {
            Log::error('LNbits createHoldInvoice failed', ['body' => $response->body()]);
            throw new RuntimeException('Failed to create hold invoice via LNbits.');
        }

        return $response->json();
    }

    public function getInvoiceStatus(string $paymentHash): array
    {
        $response = Http::withHeaders(['X-Api-Key' => $this->invoiceReadKey])
            ->get($this->url("/invoice/{$paymentHash}"));

        if ($response->failed()) {
            throw new RuntimeException("Failed to fetch hold invoice status for {$paymentHash}.");
        }

        return $response->json();
    }

    /** Reveals the preimage, settling the HTLC and completing the payment to the payee. */
    public function settleHoldInvoice(string $paymentHash, string $preimage): bool
    {
        $response = Http::withHeaders(['X-Api-Key' => $this->adminKey])
            ->post($this->url("/invoice/{$paymentHash}/settle"), [
                'preimage' => $preimage,
            ]);

        return $response->successful();
    }

    /** Cancels the hold invoice, rolling back the HTLC and refunding the payer. */
    public function cancelHoldInvoice(string $paymentHash): bool
    {
        $response = Http::withHeaders(['X-Api-Key' => $this->adminKey])
            ->post($this->url("/invoice/{$paymentHash}/cancel"));

        return $response->successful();
    }
}
