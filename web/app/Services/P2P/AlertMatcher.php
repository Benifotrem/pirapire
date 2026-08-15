<?php

namespace App\Services\P2P;

use App\DTOs\NormalizedP2POffer;
use App\Models\Alert;

/**
 * Decides whether a customer's saved Alert wants to hear about a given
 * NormalizedP2POffer — source, order type, amount range, and payment
 * method, in that order. Source-agnostic: it never looks at where the
 * offer came from beyond checking $alert->source (App\Services\P2P\Drivers
 * populate the `source` field on the DTO itself; this class just compares
 * it to the alert's preference).
 */
class AlertMatcher
{
    public static function matches(Alert $alert, NormalizedP2POffer $offer): bool
    {
        $source = $alert->source ?? 'all';
        if ($source !== 'all' && $source !== $offer->source) {
            return false;
        }

        if ($alert->order_type !== 'ANY' && $alert->order_type !== $offer->orderType) {
            return false;
        }

        $offerAmount = is_numeric($offer->fiatAmount) ? (float) $offer->fiatAmount : null;

        if ($offerAmount !== null) {
            if ($alert->min_amount !== null && $offerAmount < $alert->min_amount) {
                return false;
            }
            if ($alert->max_amount !== null && $offerAmount > $alert->max_amount) {
                return false;
            }
        }

        $paymentMethods = $alert->payment_methods ?? [];
        if (! empty($paymentMethods)) {
            $offerMethod = strtolower($offer->paymentMethod ?? '');
            $hasMatch = collect($paymentMethods)->contains(
                fn (string $m) => str_contains($offerMethod, strtolower($m)),
            );
            if (! $hasMatch) {
                return false;
            }
        }

        return true;
    }
}
