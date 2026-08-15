<?php

namespace Tests\Unit;

use App\Models\Alert;
use App\Services\RoboSats\AlertMatcher;
use Tests\TestCase;

class AlertMatcherTest extends TestCase
{
    private function order(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'type' => 0, // BUY
            'currency' => 600,
            'amount' => '100000',
            'min_amount' => null,
            'max_amount' => null,
            'payment_method' => 'Bank transfer',
            'price' => '1000000',
            'premium' => '2.5',
            'maker_nick' => 'satoshi',
            'maker_status' => 'Active',
            'created_at' => now()->toIso8601String(),
            'escrow_duration' => 10800,
        ], $overrides);
    }

    private function alert(array $overrides = []): Alert
    {
        return new Alert(array_merge([
            'order_type' => 'ANY',
            'min_amount' => null,
            'max_amount' => null,
            'payment_methods' => [],
        ], $overrides));
    }

    public function test_matches_any_order_type_by_default(): void
    {
        $this->assertTrue(AlertMatcher::matches($this->alert(), $this->order()));
    }

    public function test_rejects_mismatched_order_type(): void
    {
        $alert = $this->alert(['order_type' => 'SELL']);
        $this->assertFalse(AlertMatcher::matches($alert, $this->order(['type' => 0])));
    }

    public function test_accepts_matching_order_type(): void
    {
        $alert = $this->alert(['order_type' => 'BUY']);
        $this->assertTrue(AlertMatcher::matches($alert, $this->order(['type' => 0])));
    }

    public function test_rejects_order_below_min_amount(): void
    {
        $alert = $this->alert(['min_amount' => 200000]);
        $this->assertFalse(AlertMatcher::matches($alert, $this->order(['amount' => '100000'])));
    }

    public function test_rejects_order_above_max_amount(): void
    {
        $alert = $this->alert(['max_amount' => 50000]);
        $this->assertFalse(AlertMatcher::matches($alert, $this->order(['amount' => '100000'])));
    }

    public function test_rejects_order_without_a_matching_payment_method(): void
    {
        $alert = $this->alert(['payment_methods' => ['PIX']]);
        $this->assertFalse(AlertMatcher::matches($alert, $this->order(['payment_method' => 'Bank transfer'])));
    }

    public function test_accepts_case_insensitive_partial_payment_method_match(): void
    {
        $alert = $this->alert(['payment_methods' => ['bank']]);
        $this->assertTrue(AlertMatcher::matches($alert, $this->order(['payment_method' => 'Bank Transfer'])));
    }

    public function test_format_order_message_includes_key_fields(): void
    {
        $message = AlertMatcher::formatOrderMessage($this->order(['id' => 42]), 'PYG');

        $this->assertStringContainsString('COMPRA', $message);
        $this->assertStringContainsString('PYG', $message);
        $this->assertStringContainsString('satoshi', $message);
        $this->assertStringContainsString('robosats.com/order/42', $message);
    }
}
