<?php

namespace Tests\Unit\P2P;

use App\DTOs\NormalizedP2POffer;
use App\Services\P2P\P2PMessageFormatter;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class P2PMessageFormatterTest extends TestCase
{
    public function test_robosats_offer_includes_source_label_and_link_but_no_command_block(): void
    {
        $offer = new NormalizedP2POffer(
            id: '42',
            source: 'robosats',
            orderType: 'BUY',
            fiatAmount: '100000',
            fiatCurrency: 'PYG',
            estimatedSats: 250000,
            paymentMethod: 'Bank transfer',
            makerReputation: null,
            url: 'https://robosats.com/order/42',
            actionCommand: null,
            expiresAt: null,
        );

        $message = (new P2PMessageFormatter)->format($offer);

        $this->assertStringContainsString('🤖 [RoboSats]', $message);
        $this->assertStringContainsString('COMPRA', $message);
        $this->assertStringContainsString('100000 PYG', $message);
        $this->assertStringContainsString('250,000 sats', $message);
        $this->assertStringContainsString('Bank transfer', $message);
        $this->assertStringContainsString('[abrir](https://robosats.com/order/42)', $message);
        $this->assertStringNotContainsString('```', $message);
    }

    public function test_mostro_offer_includes_source_label_link_and_copyable_command_block(): void
    {
        $offer = new NormalizedP2POffer(
            id: 'abc123',
            source: 'mostro',
            orderType: 'SELL',
            fiatAmount: '50000',
            fiatCurrency: 'PYG',
            estimatedSats: null,
            paymentMethod: 'PIX',
            makerReputation: null,
            url: 'https://njump.me/abc123',
            actionCommand: 'mostro-cli takesell -o abc123',
            expiresAt: CarbonImmutable::create(2026, 1, 1, 12, 0),
        );

        $message = (new P2PMessageFormatter)->format($offer);

        $this->assertStringContainsString('👾 [Mostro]', $message);
        $this->assertStringContainsString('VENTA', $message);
        $this->assertStringContainsString('[abrir](https://njump.me/abc123)', $message);
        $this->assertStringContainsString("```\nmostro-cli takesell -o abc123\n```", $message);
        $this->assertStringContainsString('Vence:', $message);
    }

    public function test_omits_optional_fields_when_absent(): void
    {
        $offer = new NormalizedP2POffer(
            id: '1',
            source: 'robosats',
            orderType: 'BUY',
            fiatAmount: '1000',
            fiatCurrency: 'USD',
            estimatedSats: null,
            paymentMethod: null,
            makerReputation: null,
            url: 'https://robosats.com/order/1',
            actionCommand: null,
            expiresAt: null,
        );

        $message = (new P2PMessageFormatter)->format($offer);

        // Not "sats" alone — the offer URL is robosats.com, which
        // contains that substring too. The "≈ N sats" line uses "≈".
        $this->assertStringNotContainsString('≈', $message);
        $this->assertStringNotContainsString('Método de pago', $message);
        $this->assertStringNotContainsString('Vence', $message);
    }
}
