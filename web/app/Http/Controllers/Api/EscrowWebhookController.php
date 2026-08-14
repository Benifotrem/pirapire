<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EscrowJob;
use App\Services\Escrow\EscrowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives payment-status callbacks from LNbits when a hold invoice's HTLC
 * is accepted (i.e. the payer has funded the escrow but the payment is not
 * yet settled/finalized — that only happens on EscrowService::release()).
 */
class EscrowWebhookController extends Controller
{
    public function __construct(private readonly EscrowService $escrow) {}

    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.lnbits.webhook_secret');
        if ($secret && $request->header('X-Webhook-Secret') !== $secret) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $paymentHash = $request->input('payment_hash');
        if (! $paymentHash) {
            return response()->json(['message' => 'Missing payment_hash'], 422);
        }

        $job = EscrowJob::where('payment_hash', $paymentHash)->first();
        if (! $job) {
            Log::warning('LNbits webhook for unknown escrow payment_hash', ['payment_hash' => $paymentHash]);

            return response()->json(['message' => 'Unknown payment_hash'], 404);
        }

        if ($job->status === 'created') {
            $this->escrow->markFunded($job);
        }

        return response()->json(['status' => 'ok']);
    }
}
