<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\EscrowJob;
use App\Models\EscrowJobApplication;
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

    public function test_post_job_creates_an_open_job_with_no_invoice_yet(): void
    {
        config(['services.escrow.fee_percent' => 1.5]);
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));
        $customer = Customer::factory()->create();

        $job = $service->postJob($customer, 1000, 'Diseño de logo');

        $this->assertSame('open', $job->status);
        $this->assertSame(1000, $job->amount_sats);
        $this->assertSame(15, $job->fee_sats);
        $this->assertNull($job->funding_invoice);
        $this->assertNull($job->counterparty_customer_id);
    }

    public function test_post_job_rejects_a_non_positive_amount(): void
    {
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));
        $customer = Customer::factory()->create();

        $this->expectException(DomainException::class);
        $service->postJob($customer, 0, 'Diseño de logo');
    }

    public function test_apply_to_job_creates_a_pending_application(): void
    {
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));
        $job = EscrowJob::factory()->create(['status' => 'open', 'counterparty_customer_id' => null]);
        $freelancer = Customer::factory()->create();

        $application = $service->applyToJob($job, $freelancer, 'Puedo hacerlo en 2 días');

        $this->assertSame('pending', $application->status);
        $this->assertSame($freelancer->id, $application->freelancer_customer_id);
    }

    public function test_apply_to_job_rejects_the_creator_applying_to_their_own_job(): void
    {
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));
        $creator = Customer::factory()->create();
        $job = EscrowJob::factory()->create(['status' => 'open', 'creator_customer_id' => $creator->id, 'counterparty_customer_id' => null]);

        $this->expectException(DomainException::class);
        $service->applyToJob($job, $creator, 'Me postulo a mi propio trabajo');
    }

    public function test_apply_to_job_requires_the_job_to_be_open(): void
    {
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));
        $job = EscrowJob::factory()->create(['status' => 'funded']);
        $freelancer = Customer::factory()->create();

        $this->expectException(DomainException::class);
        $service->applyToJob($job, $freelancer, 'Llegué tarde');
    }

    public function test_accept_application_assigns_the_freelancer_and_charges_amount_plus_fee_on_the_funding_invoice(): void
    {
        $lnbits = Mockery::mock(LnbitsClient::class);
        $lnbits->shouldReceive('createInvoice')
            ->once()
            ->withArgs(fn ($amount) => $amount === 1015)
            ->andReturn(['payment_request' => 'lnbc...', 'payment_hash' => 'abc123']);

        $service = new EscrowService($lnbits);
        $creator = Customer::factory()->create();
        $job = EscrowJob::factory()->create([
            'status' => 'open', 'creator_customer_id' => $creator->id, 'counterparty_customer_id' => null,
            'amount_sats' => 1000, 'fee_sats' => 15,
        ]);
        $application = EscrowJobApplication::factory()->create(['escrow_job_id' => $job->id, 'status' => 'pending']);

        $updated = $service->acceptApplication($application, $creator);

        $this->assertSame('assigned', $updated->status);
        $this->assertSame($application->freelancer_customer_id, $updated->counterparty_customer_id);
        $this->assertSame('abc123', $updated->payment_hash);
        $this->assertSame('accepted', $application->fresh()->status);
    }

    public function test_accept_application_rejects_when_acting_customer_is_not_the_creator(): void
    {
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));
        $job = EscrowJob::factory()->create(['status' => 'open', 'counterparty_customer_id' => null]);
        $application = EscrowJobApplication::factory()->create(['escrow_job_id' => $job->id, 'status' => 'pending']);
        $someoneElse = Customer::factory()->create();

        $this->expectException(DomainException::class);
        $service->acceptApplication($application, $someoneElse);
    }

    public function test_accept_application_rejects_an_application_that_is_no_longer_pending(): void
    {
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));
        $creator = Customer::factory()->create();
        $job = EscrowJob::factory()->create(['status' => 'open', 'creator_customer_id' => $creator->id, 'counterparty_customer_id' => null]);
        $application = EscrowJobApplication::factory()->create(['escrow_job_id' => $job->id, 'status' => 'rejected']);

        $this->expectException(DomainException::class);
        $service->acceptApplication($application, $creator);
    }

    public function test_accept_application_rejects_every_other_pending_application_on_the_same_job(): void
    {
        $lnbits = Mockery::mock(LnbitsClient::class);
        $lnbits->shouldReceive('createInvoice')->once()->andReturn(['payment_request' => 'lnbc...', 'payment_hash' => 'abc123']);

        $service = new EscrowService($lnbits);
        $creator = Customer::factory()->create();
        $job = EscrowJob::factory()->create(['status' => 'open', 'creator_customer_id' => $creator->id, 'counterparty_customer_id' => null]);
        $accepted = EscrowJobApplication::factory()->create(['escrow_job_id' => $job->id, 'status' => 'pending']);
        $other = EscrowJobApplication::factory()->create(['escrow_job_id' => $job->id, 'status' => 'pending']);

        $service->acceptApplication($accepted, $creator);

        $this->assertSame('rejected', $other->fresh()->status);
    }

    public function test_mark_funded_requires_the_job_to_be_assigned(): void
    {
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));
        $job = EscrowJob::factory()->create(['status' => 'open', 'counterparty_customer_id' => null]);

        $this->expectException(DomainException::class);
        $service->markFunded($job);
    }

    public function test_deliver_requires_the_acting_customer_to_be_the_assigned_freelancer(): void
    {
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));
        $freelancer = Customer::factory()->create();
        $job = EscrowJob::factory()->create(['status' => 'funded', 'counterparty_customer_id' => $freelancer->id]);
        $someoneElse = Customer::factory()->create();

        $this->expectException(DomainException::class);
        $service->deliver($job, $someoneElse, 'lnbc1freelancerpayout');
    }

    public function test_deliver_stores_the_freelancers_invoice_and_moves_to_delivered(): void
    {
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));
        $freelancer = Customer::factory()->create();
        $job = EscrowJob::factory()->create(['status' => 'funded', 'counterparty_customer_id' => $freelancer->id]);

        $service->deliver($job, $freelancer, 'lnbc1freelancerpayout');

        $this->assertSame('delivered', $job->fresh()->status);
        $this->assertSame('lnbc1freelancerpayout', $job->fresh()->freelancer_payout_invoice);
    }

    public function test_release_requires_the_job_to_be_delivered_or_disputed(): void
    {
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));
        $creator = Customer::factory()->create();
        $job = EscrowJob::factory()->create(['status' => 'funded', 'creator_customer_id' => $creator->id]);

        $this->expectException(DomainException::class);
        $service->release($job, $creator);
    }

    public function test_release_rejects_when_acting_customer_is_not_the_creator(): void
    {
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));
        $job = EscrowJob::factory()->create(['status' => 'delivered', 'freelancer_payout_invoice' => 'lnbc1payout']);
        $someoneElse = Customer::factory()->create();

        $this->expectException(DomainException::class);
        $service->release($job, $someoneElse);
    }

    public function test_release_pays_the_freelancers_stored_invoice_and_marks_completed(): void
    {
        $lnbits = Mockery::mock(LnbitsClient::class);
        $lnbits->shouldReceive('payInvoice')->once()->with('lnbc1freelancerpayout')->andReturn(['payment_hash' => 'xyz']);

        $service = new EscrowService($lnbits);
        $creator = Customer::factory()->create();
        $job = EscrowJob::factory()->create([
            'creator_customer_id' => $creator->id,
            'status' => 'delivered',
            'freelancer_payout_invoice' => 'lnbc1freelancerpayout',
        ]);

        $service->release($job, $creator);

        $this->assertSame('completed', $job->fresh()->status);
        $this->assertNotNull($job->fresh()->settled_at);
        $this->assertSame('lnbc1freelancerpayout', $job->fresh()->payout_destination);
    }

    public function test_release_without_a_stored_or_override_invoice_fails(): void
    {
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));
        $creator = Customer::factory()->create();
        $job = EscrowJob::factory()->create(['creator_customer_id' => $creator->id, 'status' => 'delivered', 'freelancer_payout_invoice' => null]);

        $this->expectException(DomainException::class);
        $service->release($job, $creator);
    }

    public function test_release_admin_override_invoice_takes_precedence_over_the_stored_one(): void
    {
        $lnbits = Mockery::mock(LnbitsClient::class);
        $lnbits->shouldReceive('payInvoice')->once()->with('lnbc1freshoverride')->andReturn(['payment_hash' => 'xyz']);

        $service = new EscrowService($lnbits);
        // Admin path — no acting Customer, matching how the Filament resource calls this.
        $job = EscrowJob::factory()->create(['status' => 'disputed', 'freelancer_payout_invoice' => 'lnbc1expiredstale']);

        $service->release($job, null, 'lnbc1freshoverride');

        $this->assertSame('lnbc1freshoverride', $job->fresh()->payout_destination);
    }

    public function test_refund_pays_the_supplied_invoice_and_marks_refunded(): void
    {
        $lnbits = Mockery::mock(LnbitsClient::class);
        $lnbits->shouldReceive('payInvoice')->once()->with('lnbc1refundinvoice')->andReturn(['payment_hash' => 'xyz']);

        $service = new EscrowService($lnbits);
        $job = EscrowJob::factory()->create(['status' => 'delivered']);

        $service->refund($job, 'lnbc1refundinvoice');

        $this->assertSame('refunded', $job->fresh()->status);
    }

    public function test_cancel_open_job_rejects_when_acting_customer_is_not_the_creator(): void
    {
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));
        $job = EscrowJob::factory()->create(['status' => 'open', 'counterparty_customer_id' => null]);
        $someoneElse = Customer::factory()->create();

        $this->expectException(DomainException::class);
        $service->cancelOpenJob($job, $someoneElse);
    }

    public function test_cancel_open_job_marks_the_job_cancelled(): void
    {
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));
        $creator = Customer::factory()->create();
        $job = EscrowJob::factory()->create(['status' => 'open', 'creator_customer_id' => $creator->id, 'counterparty_customer_id' => null]);

        $service->cancelOpenJob($job, $creator);

        $this->assertSame('cancelled', $job->fresh()->status);
    }

    public function test_cancel_unfunded_assignment_requires_the_job_to_be_assigned(): void
    {
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));
        $job = EscrowJob::factory()->create(['status' => 'open', 'counterparty_customer_id' => null]);

        $this->expectException(DomainException::class);
        $service->cancelUnfundedAssignment($job);
    }

    public function test_open_dispute_moves_job_to_disputed_status(): void
    {
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));
        $creator = Customer::factory()->create();

        $job = EscrowJob::factory()->create([
            'creator_customer_id' => $creator->id,
            'status' => 'funded',
        ]);

        $dispute = $service->openDispute($job, $creator, 'El trabajo no fue entregado');

        $this->assertSame('disputed', $job->fresh()->status);
        $this->assertSame('open', $dispute->status);
    }

    public function test_open_dispute_allows_the_assigned_freelancer_too(): void
    {
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));
        $freelancer = Customer::factory()->create();
        $job = EscrowJob::factory()->create(['status' => 'delivered', 'counterparty_customer_id' => $freelancer->id]);

        $dispute = $service->openDispute($job, $freelancer, 'El cliente no libera el pago');

        $this->assertSame($freelancer->id, $dispute->opened_by_customer_id);
    }

    public function test_open_dispute_rejects_a_customer_who_is_not_a_party_to_the_job(): void
    {
        $service = new EscrowService(Mockery::mock(LnbitsClient::class));
        $job = EscrowJob::factory()->create(['status' => 'funded']);
        $bystander = Customer::factory()->create();

        $this->expectException(DomainException::class);
        $service->openDispute($job, $bystander, 'No tengo nada que ver con esto');
    }
}
