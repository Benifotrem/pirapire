<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EscrowJob;
use App\Models\EscrowJobApplication;
use App\Services\Lightning\LnbitsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EscrowDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard/escrow')->assertRedirect('/login');
    }

    public function test_authenticated_customer_can_view_the_board(): void
    {
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();
        EscrowJob::factory()->create(['status' => 'open', 'creator_customer_id' => $other->id, 'counterparty_customer_id' => null]);

        $response = $this->actingAs($customer, 'customer')->get('/dashboard/escrow');

        $response->assertOk();
        $response->assertViewHas('openJobs', fn ($jobs) => $jobs->count() === 1);
    }

    public function test_customer_can_publish_a_job_from_the_dashboard(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')->post('/dashboard/escrow/jobs', [
            'amount_sats' => 5000,
            'description' => 'Traducción de un sitio web',
        ])->assertRedirect();

        $this->assertDatabaseHas('escrow_jobs', [
            'creator_customer_id' => $customer->id,
            'amount_sats' => 5000,
            'status' => 'open',
        ]);
    }

    public function test_a_job_published_via_the_dashboard_is_visible_to_bot_users_via_the_same_table(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer')->post('/dashboard/escrow/jobs', [
            'amount_sats' => 5000,
            'description' => 'Traducción de un sitio web',
        ]);

        // The Telegram bot's /escrow browse and the Mini App both read the
        // same escrow_jobs table — there's only one job board, not one per
        // front end. Asserting via a fresh query (not the dashboard view
        // data) is the point: it proves the row is really there, not just
        // that the posting request succeeded.
        $this->assertSame(1, EscrowJob::where('status', 'open')->where('creator_customer_id', $customer->id)->count());
    }

    public function test_open_jobs_board_excludes_the_viewers_own_postings(): void
    {
        $customer = Customer::factory()->create();
        EscrowJob::factory()->create(['status' => 'open', 'creator_customer_id' => $customer->id, 'counterparty_customer_id' => null]);

        $response = $this->actingAs($customer, 'customer')->get('/dashboard/escrow');

        $response->assertViewHas('openJobs', fn ($jobs) => $jobs->isEmpty());
    }

    public function test_customer_can_apply_to_an_open_job(): void
    {
        $customer = Customer::factory()->create();
        $job = EscrowJob::factory()->create(['status' => 'open', 'counterparty_customer_id' => null]);

        $this->actingAs($customer, 'customer')
            ->post(route('escrow.apply', $job), ['message' => 'Puedo hacerlo hoy mismo'])
            ->assertRedirect();

        $this->assertDatabaseHas('escrow_job_applications', [
            'escrow_job_id' => $job->id,
            'freelancer_customer_id' => $customer->id,
            'status' => 'pending',
        ]);
    }

    public function test_creator_can_accept_an_application_and_gets_a_funding_invoice(): void
    {
        $customer = Customer::factory()->create();
        $job = EscrowJob::factory()->create(['status' => 'open', 'creator_customer_id' => $customer->id, 'counterparty_customer_id' => null]);
        $application = EscrowJobApplication::factory()->create(['escrow_job_id' => $job->id, 'status' => 'pending']);

        $this->mock(LnbitsClient::class, function ($mock) {
            $mock->shouldReceive('createInvoice')->once()
                ->andReturn(['payment_request' => 'lnbc1fundinginvoice', 'payment_hash' => 'hash123']);
        });

        $this->actingAs($customer, 'customer')
            ->post(route('escrow.accept', [$job, $application]))
            ->assertRedirect();

        $job->refresh();
        $this->assertSame('assigned', $job->status);
        $this->assertSame('lnbc1fundinginvoice', $job->funding_invoice);
    }

    public function test_someone_elses_job_cannot_be_accepted_or_cancelled(): void
    {
        $owner = Customer::factory()->create();
        $intruder = Customer::factory()->create();
        $job = EscrowJob::factory()->create(['status' => 'open', 'creator_customer_id' => $owner->id, 'counterparty_customer_id' => null]);
        $application = EscrowJobApplication::factory()->create(['escrow_job_id' => $job->id, 'status' => 'pending']);

        $this->mock(LnbitsClient::class, fn ($mock) => $mock->shouldNotReceive('createInvoice'));

        $this->actingAs($intruder, 'customer')
            ->post(route('escrow.accept', [$job, $application]))
            ->assertRedirect();
        $this->actingAs($intruder, 'customer')
            ->post(route('escrow.cancel', $job))
            ->assertRedirect();

        $this->assertSame('open', $job->fresh()->status);
    }

    public function test_assigned_freelancer_can_deliver_the_job(): void
    {
        $freelancer = Customer::factory()->create();
        $job = EscrowJob::factory()->create(['status' => 'funded', 'counterparty_customer_id' => $freelancer->id]);

        $this->actingAs($freelancer, 'customer')
            ->post(route('escrow.deliver', $job), ['payout_bolt11' => 'lnbc1freelancerpayout'])
            ->assertRedirect();

        $job->refresh();
        $this->assertSame('delivered', $job->status);
        $this->assertSame('lnbc1freelancerpayout', $job->freelancer_payout_invoice);
    }

    public function test_creator_can_release_a_delivered_job(): void
    {
        $customer = Customer::factory()->create();
        $job = EscrowJob::factory()->create(['creator_customer_id' => $customer->id, 'status' => 'delivered', 'freelancer_payout_invoice' => 'lnbc1freelancerpayout']);

        $this->mock(LnbitsClient::class, function ($mock) {
            $mock->shouldReceive('payInvoice')->once()->with('lnbc1freelancerpayout')->andReturn(['payment_hash' => 'x']);
        });

        $this->actingAs($customer, 'customer')
            ->post(route('escrow.release', $job))
            ->assertRedirect();

        $this->assertSame('completed', $job->fresh()->status);
    }

    public function test_either_party_can_open_a_dispute(): void
    {
        $freelancer = Customer::factory()->create();
        $job = EscrowJob::factory()->create(['status' => 'funded', 'counterparty_customer_id' => $freelancer->id]);

        $this->actingAs($freelancer, 'customer')
            ->post(route('escrow.dispute', $job), ['reason' => 'el cliente no responde'])
            ->assertRedirect();

        $this->assertSame('disputed', $job->fresh()->status);
        $this->assertDatabaseHas('escrow_disputes', [
            'escrow_job_id' => $job->id,
            'opened_by_customer_id' => $freelancer->id,
        ]);
    }
}
