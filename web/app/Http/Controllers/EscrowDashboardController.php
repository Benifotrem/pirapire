<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\EscrowJob;
use App\Models\EscrowJobApplication;
use App\Services\Escrow\EscrowProofStorage;
use App\Services\Escrow\EscrowService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Web-dashboard counterpart to App\Http\Controllers\MiniApp\CustomerController —
 * same App\Services\Escrow\EscrowService calls, same escrow_jobs/escrow_job_applications
 * rows, just reached over an authenticated browser session (auth:customer) with
 * plain form posts instead of the Mini App's Telegram-initData-authenticated JSON
 * API. Publishing, applying, accepting, delivering, releasing, and disputing a job
 * work the same regardless of which of the two front ends was used to do it —
 * there is only one job board, not two.
 */
class EscrowDashboardController extends Controller
{
    public function __construct(
        private readonly EscrowService $escrow,
        private readonly EscrowProofStorage $proofStorage,
    ) {}

    private function customer(): Customer
    {
        return Auth::guard('customer')->user();
    }

    public function board(): View
    {
        $customer = $this->customer();

        return view('escrow.board', [
            'customer' => $customer,
            'feePercent' => $this->escrow->feePercent(),
            'openJobs' => EscrowJob::where('status', 'open')
                ->where('creator_customer_id', '!=', $customer->id)
                ->whereDoesntHave('applications', fn ($q) => $q->where('freelancer_customer_id', $customer->id))
                ->latest()
                ->limit(50)
                ->get(),
            'myJobs' => $customer->escrowJobsCreated()
                ->with(['applications' => fn ($q) => $q->where('status', 'pending')->with('freelancer:id,display_name')])
                ->latest()
                ->limit(50)
                ->get(),
            'freelanceJobs' => $customer->escrowJobsAsFreelancer()->latest()->limit(50)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount_sats' => 'required|integer|min:1',
            'description' => 'required|string|max:500',
        ]);

        try {
            $this->escrow->postJob($this->customer(), $validated['amount_sats'], $validated['description']);
        } catch (DomainException $e) {
            return back()->withErrors(['amount_sats' => $e->getMessage()]);
        }

        return back()->with('status', 'Trabajo publicado en el tablón.');
    }

    public function cancel(EscrowJob $job): RedirectResponse
    {
        try {
            $this->escrow->cancelOpenJob($job, $this->customer());
        } catch (DomainException $e) {
            return back()->withErrors(['escrow' => $e->getMessage()]);
        }

        return back()->with('status', 'Trabajo cancelado.');
    }

    public function apply(Request $request, EscrowJob $job): RedirectResponse
    {
        $validated = $request->validate(['message' => 'required|string|max:1000']);

        try {
            $this->escrow->applyToJob($job, $this->customer(), $validated['message']);
        } catch (DomainException $e) {
            return back()->withErrors(['escrow' => $e->getMessage()]);
        }

        return back()->with('status', 'Te postulaste al trabajo.');
    }

    public function accept(EscrowJob $job, EscrowJobApplication $application): RedirectResponse
    {
        abort_unless($application->escrow_job_id === $job->id, 404);

        try {
            $this->escrow->acceptApplication($application, $this->customer());
        } catch (DomainException $e) {
            return back()->withErrors(['escrow' => $e->getMessage()]);
        } catch (RuntimeException) {
            return back()->withErrors(['escrow' => 'No se pudo contactar al proveedor de pagos. Probá de nuevo en unos minutos.']);
        }

        return back()->with('status', 'Freelancer elegido. Factura de fondeo generada abajo.');
    }

    public function deliver(Request $request, EscrowJob $job): RedirectResponse
    {
        $validated = $request->validate([
            'payout_bolt11' => 'required|string',
            'proof' => 'nullable|image|max:5120',
        ]);

        $proofPath = $request->hasFile('proof') ? $this->proofStorage->store($request->file('proof')) : null;

        try {
            $this->escrow->deliver($job, $this->customer(), $validated['payout_bolt11'], $proofPath);
        } catch (DomainException $e) {
            if ($proofPath) {
                $this->proofStorage->delete($proofPath);
            }

            return back()->withErrors(['escrow' => $e->getMessage()]);
        }

        return back()->with('status', 'Trabajo marcado como entregado.');
    }

    /** Only the job's creator or its assigned freelancer may view the delivery proof. */
    public function proof(EscrowJob $job): StreamedResponse
    {
        $customerId = $this->customer()->id;
        abort_unless(in_array($customerId, [$job->creator_customer_id, $job->counterparty_customer_id], true), 403);
        abort_unless((bool) $job->proof_path, 404);

        return $this->proofStorage->response($job->proof_path);
    }

    public function release(EscrowJob $job): RedirectResponse
    {
        try {
            $this->escrow->release($job, $this->customer());
        } catch (DomainException $e) {
            return back()->withErrors(['escrow' => $e->getMessage()]);
        } catch (RuntimeException) {
            return back()->withErrors(['escrow' => 'No se pudo contactar al proveedor de pagos. Probá de nuevo en unos minutos.']);
        }

        return back()->with('status', 'Pago liberado al freelancer.');
    }

    public function dispute(Request $request, EscrowJob $job): RedirectResponse
    {
        $validated = $request->validate(['reason' => 'required|string|max:1000']);

        try {
            $this->escrow->openDispute($job, $this->customer(), $validated['reason']);
        } catch (DomainException $e) {
            return back()->withErrors(['escrow' => $e->getMessage()]);
        }

        return back()->with('status', 'Disputa abierta. Un admin la va a revisar.');
    }
}
