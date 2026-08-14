<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\EscrowJob;
use App\Services\Escrow\EscrowService;
use App\Services\Lightning\LnbitsClient;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class EscrowServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fee_is_calculated_at_the_configured_percentage(): void
    {
        config(['services.escrow.fee_percent' => 1.5]);
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));

        $this->assertSame(15, $service->calculateFee(1000));
        $this->assertSame(150, $service->calculateFee(10000));
    }

    public function test_create_job_charges_amount_plus_fee_on_the_funding_invoice(): void
    {
        $lnbits = Mockery::mock(LnbitsClient::class);
        $lnbits->shouldReceive('createInvoice')
            ->once()
            ->withArgs(fn ($amount) => $amount === 1015)
            ->andReturn(['payment_request' => 'lnbc...', 'payment_hash' => 'abc123']);

        $service = new EscrowService($lnbits);
        $customer = Customer::factory()->create();

        $job = $service->createJob($customer, 1000, 'Diseño de logo');

        $this->assertSame('created', $job->status);
        $this->assertSame(1000, $job->amount_sats);
        $this->assertSame(15, $job->fee_sats);
        $this->assertSame('abc123', $job->payment_hash);
    }

    public function test_release_requires_the_job_to_be_funded_or_disputed(): void
    {
        $lnbits = Mockery::mock(LnbitsClient::class);
        $service = new EscrowService($lnbits);
        $customer = Customer::factory()->create();

        $job = EscrowJob::factory()->create([
            'creator_customer_id' => $customer->id,
            'status' => 'created',
        ]);

        $this->expectException(DomainException::class);
        $service->release($job, 'lnbc1payoutinvoice');
    }

    public function test_release_pays_the_supplied_invoice_and_marks_completed(): void
    {
        $lnbits = Mockery::mock(LnbitsClient::class);
        $lnbits->shouldReceive('payInvoice')->once()->with('lnbc1payoutinvoice')->andReturn(['payment_hash' => 'xyz']);

        $service = new EscrowService($lnbits);
        $customer = Customer::factory()->create();

        $job = EscrowJob::factory()->create([
            'creator_customer_id' => $customer->id,
            'status' => 'funded',
        ]);

        $service->release($job, 'lnbc1payoutinvoice');

        $this->assertSame('completed', $job->fresh()->status);
        $this->assertNotNull($job->fresh()->settled_at);
        $this->assertSame('lnbc1payoutinvoice', $job->fresh()->payout_destination);
    }

    public function test_refund_pays_the_supplied_invoice_and_marks_refunded(): void
    {
        $lnbits = Mockery::mock(LnbitsClient::class);
        $lnbits->shouldReceive('payInvoice')->once()->with('lnbc1refundinvoice')->andReturn(['payment_hash' => 'xyz']);

        $service = new EscrowService($lnbits);
        $customer = Customer::factory()->create();

        $job = EscrowJob::factory()->create([
            'creator_customer_id' => $customer->id,
            'status' => 'funded',
        ]);

        $service->refund($job, 'lnbc1refundinvoice');

        $this->assertSame('refunded', $job->fresh()->status);
    }

    public function test_open_dispute_moves_job_to_disputed_status(): void
    {
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));
        $customer = Customer::factory()->create();

        $job = EscrowJob::factory()->create([
            'creator_customer_id' => $customer->id,
            'status' => 'funded',
        ]);

        $dispute = $service->openDispute($job, $customer, 'El trabajo no fue entregado');

        $this->assertSame('disputed', $job->fresh()->status);
        $this->assertSame('open', $dispute->status);
    }
}
