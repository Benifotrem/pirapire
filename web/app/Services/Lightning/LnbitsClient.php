<?php

namespace App\Services\Lightning;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin wrapper around a self-hosted LNbits instance's core REST API
 * (POST /api/v1/payments), used by App\Services\Escrow\EscrowService to
 * fund and pay out escrow jobs.
 *
 * There is no "hold invoice" extension in LNbits — that was this app's
 * original (incorrect) design assumption. LNbits only exposes regular
 * invoices: a funding invoice settles into the platform's wallet as soon
 * as it's paid, and "releasing"/"refunding" an escrow means the platform
 * actively sends a new outbound payment to a bolt11 invoice supplied by
 * the freelancer/client at that time — not revealing a preimage on an
 * invoice still in flight. See EscrowService for the resulting state
 * machine.
 */
class LnbitsClient
{
    private readonly string $baseUrl;

    private readonly ?string $adminKey;

    private readonly ?string $invoiceReadKey;

    public function __construct(
        ?string $baseUrl = null,
        ?string $adminKey = null,
        ?string $invoiceReadKey = null,
    ) {
        // Readonly properties can only be assigned once — constructor
        // promotion (`private readonly ?string $adminKey = null`) would
        // already consume that single assignment, so a later `??=` to
        // fall back to config() throws "Cannot modify readonly property".
        // Plain parameters + one explicit assignment each avoids that.
        $this->baseUrl = rtrim($baseUrl ?? config('services.lnbits.base_url'), '/');
        $this->adminKey = $adminKey ?? config('services.lnbits.admin_key');
        $this->invoiceReadKey = $invoiceReadKey ?? config('services.lnbits.invoice_read_key');
    }

    private function url(string $path): string
    {
        return $this->baseUrl.'/api/v1'.$path;
    }

    /**
     * Creates a regular incoming invoice (funding invoice) for the given
     * amount. Uses the invoice/read key, since creating an invoice only
     * needs receive permission, not spend permission.
     */
    public function createInvoice(int $amountSats, string $memo, ?string $webhookUrl = null): array
    {
        $response = Http::withHeaders(['X-Api-Key' => $this->invoiceReadKey])
            ->post($this->url('/payments'), array_filter([
                'out' => false,
                'amount' => $amountSats,
                'memo' => $memo,
                'webhook' => $webhookUrl,
            ], fn ($value) => $value !== null));

        if ($response->failed()) {
            Log::error('LNbits createInvoice failed', ['body' => $response->body()]);
            throw new RuntimeException('Failed to create invoice via LNbits.');
        }

        return $response->json();
    }

    /**
     * Pays a bolt11 invoice out of the platform's LNbits wallet — used to
     * release funds to a freelancer or refund a client. Uses the admin
     * key, since sending payments needs spend permission.
     */
    public function payInvoice(string $bolt11): array
    {
        $response = Http::withHeaders(['X-Api-Key' => $this->adminKey])
            ->post($this->url('/payments'), [
                'out' => true,
                'bolt11' => $bolt11,
            ]);

        if ($response->failed()) {
            Log::error('LNbits payInvoice failed', ['body' => $response->body()]);
            throw new RuntimeException('Failed to pay invoice via LNbits.');
        }

        return $response->json();
    }
}
