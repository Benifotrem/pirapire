<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;

class VipApiController extends Controller
{
    public function show(string $whatsappNumber): JsonResponse
    {
        $customer = Customer::where('whatsapp_number', $whatsappNumber)->first();

        if (! $customer) {
            return response()->json(['is_vip' => false, 'expires_at' => null]);
        }

        $activeSubscription = $customer->vipSubscriptions()
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest('expires_at')
            ->first();

        return response()->json([
            'is_vip' => (bool) $activeSubscription,
            'expires_at' => $activeSubscription?->expires_at?->toIso8601String(),
        ]);
    }
}
