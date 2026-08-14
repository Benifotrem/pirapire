<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\EscrowJob;
use App\Services\Escrow\EscrowService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Consumed by the WhatsApp bot's `!escrow` command handlers. */
class EscrowApiController extends Controller
{
    public function __construct(private readonly EscrowService $escrow) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'whatsapp_number' => 'required|string',
            'amount_sats' => 'required|integer|min:1',
            'description' => 'required|string|max:500',
        ]);

        $customer = Customer::firstOrCreate(['whatsapp_number' => $validated['whatsapp_number']]);

        try {
            $job = $this->escrow->createJob($customer, $validated['amount_sats'], $validated['description']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->present($job), 201);
    }

    public function show(EscrowJob $job): JsonResponse
    {
        return response()->json($this->present($job));
    }

    public function release(Request $request, EscrowJob $job): JsonResponse
    {
        $validated = $request->validate([
            'payout_bolt11' => 'required|string',
        ]);

        return $this->performAction($job, fn () => $this->escrow->release($job, $validated['payout_bolt11']));
    }

    public function dispute(Request $request, EscrowJob $job): JsonResponse
    {
        $validated = $request->validate([
            'requested_by' => 'required|string',
            'reason' => 'nullable|string|max:500',
        ]);

        $customer = Customer::firstOrCreate(['whatsapp_number' => $validated['requested_by']]);

        return $this->performAction(
            $job,
            fn () => $this->escrow->openDispute($job, $customer, $validated['reason'] ?? 'Disputa abierta desde WhatsApp'),
        );
    }

    public function cancel(Request $request, EscrowJob $job): JsonResponse
    {
        return $this->performAction($job, fn () => $this->escrow->cancelUnfunded($job));
    }

    private function performAction(EscrowJob $job, callable $action): JsonResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->present($job->fresh()));
    }

    private function present(EscrowJob $job): array
    {
        return [
            'id' => $job->id,
            'status' => $job->status,
            'amount_sats' => $job->amount_sats,
            'fee_sats' => $job->fee_sats,
            'funding_invoice' => $job->funding_invoice,
            'expires_at' => $job->expires_at->toIso8601String(),
        ];
    }
}
