<?php

namespace App\Services\RoboSats;

use App\Models\Alert;

/** RoboSats numeric order type: 0 = BUY (maker wants to buy BTC), 1 = SELL. */
class AlertMatcher
{
    public static function orderTypeLabel(int $type): string
    {
        return $type === 0 ? 'BUY' : 'SELL';
    }

    /** @param array<string, mixed> $order */
    public static function matches(Alert $alert, array $order): bool
    {
        if ($alert->order_type !== 'ANY' && $alert->order_type !== self::orderTypeLabel($order['type'])) {
            return false;
        }

        $orderAmount = (float) ($order['amount'] ?? $order['max_amount'] ?? $order['min_amount'] ?? 0);

        if ($alert->min_amount !== null && $orderAmount < $alert->min_amount) {
            return false;
        }
        if ($alert->max_amount !== null && $orderAmount > $alert->max_amount) {
            return false;
        }

        $paymentMethods = $alert->payment_methods ?? [];
        if (! empty($paymentMethods)) {
            $orderMethod = strtolower($order['payment_method'] ?? '');
            $hasMatch = collect($paymentMethods)->contains(
                fn (string $m) => str_contains($orderMethod, strtolower($m)),
            );
            if (! $hasMatch) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $order */
    public static function formatOrderMessage(array $order, string $currency): string
    {
        $type = self::orderTypeLabel($order['type']);
        $emoji = $type === 'BUY' ? '🟢' : '🔴';
        $amount = $order['amount'] ?? (($order['min_amount'] ?? '?').'-'.($order['max_amount'] ?? '?'));

        return implode("\n", [
            "{$emoji} *Nueva orden RoboSats P2P*",
            'Tipo: '.($type === 'BUY' ? 'COMPRA' : 'VENTA').' de BTC',
            "Monto: {$amount} {$currency}",
            "Precio: {$order['price']} {$currency} (premium {$order['premium']}%)",
            "Método de pago: {$order['payment_method']}",
            "Maker: {$order['maker_nick']}",
            "Ver orden: https://robosats.com/order/{$order['id']}",
        ]);
    }
}
