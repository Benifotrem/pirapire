<?php

namespace Tests\Unit;

use App\Services\Lightning\LnbitsClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verifies LnbitsClient sends exactly the request shape confirmed against
 * a real LNbits instance's own API docs (POST /api/v1/payments) — not
 * guessed from documentation alone.
 */
class LnbitsClientTest extends TestCase
{
    public function test_create_invoice_posts_the_confirmed_incoming_payment_shape(): void
    {
        Http::fake([
            'lnbits.test/api/v1/payments' => Http::response([
                'payment_hash' => 'abc123',
                'payment_request' => 'lnbc1...',
            ], 201),
        ]);

        $client = new LnbitsClient('http://lnbits.test', 'admin-key', 'invoice-key');
        $result = $client->createInvoice(1015, 'Pirapire escrow: test job', 'https://pirapire.pro/api/escrow/webhook');

        $this->assertSame('abc123', $result['payment_hash']);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://lnbits.test/api/v1/payments'
                && $request->method() === 'POST'
                && $request->hasHeader('X-Api-Key', 'invoice-key')
                && $request['out'] === false
                && $request['amount'] === 1015
                && $request['memo'] === 'Pirapire escrow: test job'
                && $request['webhook'] === 'https://pirapire.pro/api/escrow/webhook';
        });
    }

    public function test_pay_invoice_posts_the_confirmed_outgoing_payment_shape(): void
    {
        Http::fake([
            'lnbits.test/api/v1/payments' => Http::response(['payment_hash' => 'def456'], 201),
        ]);

        $client = new LnbitsClient('http://lnbits.test', 'admin-key', 'invoice-key');
        $result = $client->payInvoice('lnbc1payoutinvoice');

        $this->assertSame('def456', $result['payment_hash']);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://lnbits.test/api/v1/payments'
                && $request->method() === 'POST'
                && $request->hasHeader('X-Api-Key', 'admin-key')
                && $request['out'] === true
                && $request['bolt11'] === 'lnbc1payoutinvoice';
        });
    }

    public function test_get_wallet_details_uses_the_read_only_invoice_key(): void
    {
        Http::fake([
            'lnbits.test/api/v1/wallet' => Http::response(['id' => 'w1', 'name' => 'Pirapire', 'balance' => 150000], 200),
        ]);

        $client = new LnbitsClient('http://lnbits.test', 'admin-key', 'invoice-key');
        $result = $client->getWalletDetails();

        $this->assertSame(150000, $result['balance']);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://lnbits.test/api/v1/wallet'
                && $request->method() === 'GET'
                && $request->hasHeader('X-Api-Key', 'invoice-key');
        });
    }
}
