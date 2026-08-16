<?php

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\EscrowJob;
use App\Models\EscrowJobApplication;
use App\Services\Escrow\EscrowProofStorage;
use App\Services\Escrow\EscrowService;
use App\Services\Mempool\MempoolClient;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * JSON API behind the customer Mini App (resources/views/miniapp/customer.blade.php).
 * Every action here is also reachable as a /command in
 * App\Services\Bot\CustomerCommandRouter — the Mini App is a richer UI over
 * the same EscrowService/Alert/MempoolClient calls, not a separate system.
 * Auth is done by AuthenticateCustomerMiniApp, which resolves the Customer
 * from Telegram's initData and attaches it as the `customer` request attribute.
 */
class CustomerController extends Controller
{
    public function __construct(
        private readonly EscrowService $escrow,
        private readonly MempoolClient $mempool,
        private readonly EscrowProofStorage $proofStorage,
    ) {}

    private function customer(Request $request): Customer
    {
        return $request->attributes->get('customer');
    }

    public function me(Request $request): JsonResponse
    {
        $customer = $this->customer($request);

        return response()->json([
            'display_name' => $customer->display_name,
            'telegram_chat_id' => $customer->telegram_chat_id,
            'is_vip' => $customer->isVip(),
            'vip_expires_at' => $customer->vipSubscriptions()
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->latest('expires_at')
                ->first()
                ?->expires_at,
        ]);
    }

    public function mempool(): JsonResponse
    {
        $stats = $this->mempool->stats();

        if ($stats === null) {
            return response()->json(['message' => 'mempool.space no disponible'], 503);
        }

        return response()->json($stats);
    }

    public function alerts(Request $request): JsonResponse
    {
        return response()->json($this->customer($request)->alerts()->latest()->get());
    }

    public function storeAlert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency' => 'required|in:PYG,USD',
            'source' => 'nullable|in:robosats,mostro,all',
            'order_type' => 'required|in:BUY,SELL,ANY',
            'min_amount' => 'nullable|integer|min:0',
            'max_amount' => 'nullable|integer|gte:min_amount',
            'payment_methods' => 'nullable|array',
            'payment_methods.*' => 'string|max:64',
        ]);

        $alert = $this->customer($request)->alerts()->create($validated);

        return response()->json($alert, 201);
    }

    public function toggleAlert(Request $request, Alert $alert): JsonResponse
    {
        abort_unless($alert->customer_id === $this->customer($request)->id, 403);

        $alert->update(['is_active' => ! $alert->is_active]);

        return response()->json($alert);
    }

    public function destroyAlert(Request $request, Alert $alert): JsonResponse
    {
        abort_unless($alert->customer_id === $this->customer($request)->id, 403);

        $alert->delete();

        return response()->json(['status' => 'deleted']);
    }

    /** Jobs the current customer posted as a client. */
    public function escrowJobs(Request $request): JsonResponse
    {
        return response()->json(
            $this->customer($request)->escrowJobsCreated()->latest()->limit(50)->get(),
        );
    }

    /** Jobs the current customer is doing as a freelancer. */
    public function myFreelanceJobs(Request $request): JsonResponse
    {
        return response()->json(
            $this->customer($request)->escrowJobsAsFreelancer()->latest()->limit(50)->get(),
        );
    }

    /** Open jobs available to apply to — everyone's except the current customer's own postings. */
    public function openEscrowJobs(Request $request): JsonResponse
    {
        return response()->json(
            EscrowJob::where('status', 'open')
                ->where('creator_customer_id', '!=', $this->customer($request)->id)
                ->latest()
                ->limit(50)
                ->get(),
        );
    }

    /** Either party to the job — client or assigned freelancer — can view it. */
    public function showEscrowJob(Request $request, EscrowJob $job): JsonResponse
    {
        $this->abortUnlessParty($request, $job);

        return response()->json($job->load(['applications' => fn ($q) => $q->where('status', 'pending')]));
    }

    public function storeEscrowJob(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount_sats' => 'required|integer|min:1',
            'description' => 'required|string|max:500',
        ]);

        try {
            $job = $this->escrow->postJob($this->customer($request), $validated['amount_sats'], $validated['description']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($job, 201);
    }

    public function cancelEscrowJob(Request $request, EscrowJob $job): JsonResponse
    {
        $customer = $this->customer($request);
        abort_unless($job->creator_customer_id === $customer->id, 403);

        try {
            $this->escrow->cancelOpenJob($job, $customer);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($job->fresh());
    }

    /** A freelancer pitches themselves for an open job. */
    public function applyToEscrowJob(Request $request, EscrowJob $job): JsonResponse
    {
        $validated = $request->validate(['message' => 'required|string|max:1000']);

        try {
            $application = $this->escrow->applyToJob($job, $this->customer($request), $validated['message']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($application, 201);
    }

    /** The client reviews pending applications on their own job. */
    public function jobApplications(Request $request, EscrowJob $job): JsonResponse
    {
        abort_unless($job->creator_customer_id === $this->customer($request)->id, 403);

        return response()->json(
            $job->applications()->where('status', 'pending')->with('freelancer:id,display_name')->get(),
        );
    }

    /** The client picks one applicant — assigns them and generates the funding invoice. */
    public function acceptApplication(Request $request, EscrowJob $job, EscrowJobApplication $application): JsonResponse
    {
        abort_unless($application->escrow_job_id === $job->id, 404);

        try {
            $job = $this->escrow->acceptApplication($application, $this->customer($request));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException) {
            return response()->json(['message' => 'No se pudo contactar al proveedor de pagos. Probá de nuevo en unos minutos.'], 503);
        }

        return response()->json($job);
    }

    /** The assigned freelancer marks the job done and submits their own payout invoice. */
    public function deliverEscrowJob(Request $request, EscrowJob $job): JsonResponse
    {
        $customer = $this->customer($request);
        abort_unless($job->counterparty_customer_id === $customer->id, 403);

        $validated = $request->validate([
            'payout_bolt11' => 'required|string',
            'proof' => 'nullable|image|max:5120',
        ]);

        $proofPath = $request->hasFile('proof') ? $this->proofStorage->store($request->file('proof')) : null;

        try {
            $this->escrow->deliver($job, $customer, $validated['payout_bolt11'], $proofPath);
        } catch (DomainException $e) {
            if ($proofPath) {
                $this->proofStorage->delete($proofPath);
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($job->fresh());
    }

    /** Only the job's creator or its assigned freelancer may view the delivery proof. */
    public function escrowJobProof(Request $request, EscrowJob $job): StreamedResponse
    {
        $this->abortUnlessParty($request, $job);
        abort_unless((bool) $job->proof_path, 404);

        return $this->proofStorage->response($job->proof_path);
    }

    public function releaseEscrowJob(Request $request, EscrowJob $job): JsonResponse
    {
        $customer = $this->customer($request);
        abort_unless($job->creator_customer_id === $customer->id, 403);

        try {
            $this->escrow->release($job, $customer);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException) {
            return response()->json(['message' => 'No se pudo contactar al proveedor de pagos. Probá de nuevo en unos minutos.'], 503);
        }

        return response()->json($job->fresh());
    }

    public function disputeEscrowJob(Request $request, EscrowJob $job): JsonResponse
    {
        $customer = $this->customer($request);
        $this->abortUnlessParty($request, $job);

        $validated = $request->validate(['reason' => 'required|string|max:1000']);

        try {
            $this->escrow->openDispute($job, $customer, $validated['reason']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($job->fresh());
    }

    private function abortUnlessParty(Request $request, EscrowJob $job): void
    {
        $customerId = $this->customer($request)->id;
        abort_unless(in_array($customerId, [$job->creator_customer_id, $job->counterparty_customer_id], true), 403);
    }
}
