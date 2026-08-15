<?php

namespace Tests\Unit\P2P;

use App\DTOs\NormalizedP2POffer;
use App\Models\Alert;
use App\Services\P2P\AlertMatcher;
use Tests\TestCase;

class AlertMatcherTest extends TestCase
{
    private function offer(array $overrides = []): NormalizedP2POffer
    {
        $attrs = array_merge([
            'id' => '1',
            'source' => 'robosats',
            'orderType' => 'BUY',
            'fiatAmount' => '100000',
            'fiatCurrency' => 'PYG',
            'estimatedSats' => 250000,
            'paymentMethod' => 'Bank transfer',
            'makerReputation' => null,
            'url' => 'https://robosats.com/order/1',
            'actionCommand' => null,
            'expiresAt' => null,
        ], $overrides);

        return new NormalizedP2POffer(...$attrs);
    }

    private function alert(array $overrides = []): Alert
    {
        return new Alert(array_merge([
            'source' => 'all',
            'order_type' => 'ANY',
            'min_amount' => null,
            'max_amount' => null,
            'payment_methods' => [],
        ], $overrides));
    }

    public function test_matches_any_order_type_and_source_by_default(): void
    {
        $this->assertTrue(AlertMatcher::matches($this->alert(), $this->offer()));
    }

    public function test_rejects_mismatched_order_type(): void
    {
        $alert = $this->alert(['order_type' => 'SELL']);
        $this->assertFalse(AlertMatcher::matches($alert, $this->offer(['orderType' => 'BUY'])));
    }

    public function test_accepts_matching_order_type(): void
    {
        $alert = $this->alert(['order_type' => 'BUY']);
        $this->assertTrue(AlertMatcher::matches($alert, $this->offer(['orderType' => 'BUY'])));
    }

    public function test_rejects_offer_from_an_unwanted_source(): void
    {
        $alert = $this->alert(['source' => 'mostro']);
        $this->assertFalse(AlertMatcher::matches($alert, $this->offer(['source' => 'robosats'])));
    }

    public function test_accepts_offer_from_the_wanted_source(): void
    {
        $alert = $this->alert(['source' => 'mostro']);
        $this->assertTrue(AlertMatcher::matches($alert, $this->offer(['source' => 'mostro'])));
    }

    public function test_source_all_matches_every_source(): void
    {
        $alert = $this->alert(['source' => 'all']);
        $this->assertTrue(AlertMatcher::matches($alert, $this->offer(['source' => 'robosats'])));
        $this->assertTrue(AlertMatcher::matches($alert, $this->offer(['source' => 'mostro'])));
    }

    public function test_rejects_offer_below_min_amount(): void
    {
        $alert = $this->alert(['min_amount' => 200000]);
        $this->assertFalse(AlertMatcher::matches($alert, $this->offer(['fiatAmount' => '100000'])));
    }

    public function test_rejects_offer_above_max_amount(): void
    {
        $alert = $this->alert(['max_amount' => 50000]);
        $this->assertFalse(AlertMatcher::matches($alert, $this->offer(['fiatAmount' => '100000'])));
    }

    public function test_a_non_numeric_fiat_amount_skips_the_range_check_instead_of_erroring(): void
    {
        $alert = $this->alert(['min_amount' => 200000]);
        $this->assertTrue(AlertMatcher::matches($alert, $this->offer(['fiatAmount' => '50000-500000'])));
    }

    public function test_rejects_offer_without_a_matching_payment_method(): void
    {
        $alert = $this->alert(['payment_methods' => ['PIX']]);
        $this->assertFalse(AlertMatcher::matches($alert, $this->offer(['paymentMethod' => 'Bank transfer'])));
    }

    public function test_accepts_case_insensitive_partial_payment_method_match(): void
    {
        $alert = $this->alert(['payment_methods' => ['bank']]);
        $this->assertTrue(AlertMatcher::matches($alert, $this->offer(['paymentMethod' => 'Bank Transfer'])));
    }
}
