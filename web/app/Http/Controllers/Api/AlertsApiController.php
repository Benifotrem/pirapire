<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Consumed by the WhatsApp bot's RoboSats poller to fan out matching alerts. */
class AlertsApiController extends Controller
{
    public function subscribers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency' => 'required|in:PYG,USD',
        ]);

        $alerts = Alert::query()
            ->with('customer')
            ->where('currency', $validated['currency'])
            ->where('is_active', true)
            ->whereHas('customer', fn ($q) => $q->whereNotNull('whatsapp_number'))
            ->get();

        $payload = $alerts->map(function (Alert $alert) {
            return [
                'whatsapp_number' => $alert->customer->whatsapp_number,
                'is_vip' => $alert->customer->isVip(),
                'currency' => $alert->currency,
                'order_type' => $alert->order_type,
                'min_amount' => $alert->min_amount,
                'max_amount' => $alert->max_amount,
                'payment_methods' => $alert->payment_methods ?? [],
            ];
        });

        return response()->json($payload);
    }
}
