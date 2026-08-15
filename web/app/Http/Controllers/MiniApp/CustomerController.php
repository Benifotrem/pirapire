<?php

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Customer;
use App\Models\EscrowJob;
use App\Services\Escrow\EscrowService;
use App\Services\Mempool\MempoolClient;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

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

    public function escrowJobs(Request $request): JsonResponse
    {
        return response()->json(
            $this->customer($request)->escrowJobsCreated()->latest()->limit(50)->get(),
        );
    }

    public function showEscrowJob(Request $request, EscrowJob $job): JsonResponse
    {
        abort_unless($job->creator_customer_id === $this->customer($request)->id, 403);

        return response()->json($job);
    }

    public function storeEscrowJob(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount_sats' => 'required|integer|min:1',
            'description' => 'required|string|max:500',
        ]);

        try {
            $job = $this->escrow->createJob($this->customer($request), $validated['amount_sats'], $validated['description']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException) {
            return response()->json(['message' => 'No se pudo contactar al proveedor de pagos. Probá de nuevo en unos minutos.'], 503);
        }

        return response()->json($job, 201);
    }

    public function releaseEscrowJob(Request $request, EscrowJob $job): JsonResponse
    {
        abort_unless($job->creator_customer_id === $this->customer($request)->id, 403);

        $validated = $request->validate(['payout_bolt11' => 'required|string']);

        try {
            $this->escrow->release($job, $validated['payout_bolt11']);
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
        abort_unless($job->creator_customer_id === $customer->id, 403);

        $validated = $request->validate(['reason' => 'required|string|max:1000']);

        try {
            $this->escrow->openDispute($job, $customer, $validated['reason']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($job->fresh());
    }
}
