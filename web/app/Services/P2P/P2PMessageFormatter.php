<?php

namespace App\Services\P2P;

use App\DTOs\NormalizedP2POffer;

/**
 * Turns a NormalizedP2POffer into the Telegram message text sent to a
 * matching subscriber (App\Jobs\SendP2POfferAlert). Sent with Markdown
 * parse mode (see App\Services\Telegram\TelegramBotClient::sendMessage)
 * so the source label, the offer link, and — for Mostro — the copyable
 * mostro-cli command all render as intended instead of showing up as
 * literal asterisks/brackets/backticks.
 */
class P2PMessageFormatter
{
    private const SOURCE_LABELS = [
        'robosats' => '🤖 [RoboSats]',
        'mostro' => '👾 [Mostro]',
    ];

    public function format(NormalizedP2POffer $offer): string
    {
        $emoji = $offer->orderType === 'BUY' ? '🟢' : '🔴';
        $typeLabel = $offer->orderType === 'BUY' ? 'COMPRA' : 'VENTA';
        $sourceLabel = self::SOURCE_LABELS[$offer->source] ?? "[{$offer->source}]";

        $lines = [
            "{$emoji} *Nueva oferta P2P* {$sourceLabel}",
            "Tipo: {$typeLabel} de BTC",
            "Monto: {$offer->fiatAmount} {$offer->fiatCurrency}",
        ];

        if ($offer->estimatedSats !== null) {
            $lines[] = '≈ '.number_format($offer->estimatedSats).' sats';
        }
        if ($offer->paymentMethod !== null) {
            $lines[] = "Método de pago: {$offer->paymentMethod}";
        }
        if ($offer->makerReputation !== null) {
            $lines[] = "Reputación: {$offer->makerReputation}";
        }
        if ($offer->expiresAt !== null) {
            $lines[] = 'Vence: '.$offer->expiresAt->translatedFormat('d/m/Y H:i');
        }

        $lines[] = '';
        $lines[] = "Ver oferta: [abrir]({$offer->url})";

        if ($offer->actionCommand !== null) {
            $lines[] = '';
            $lines[] = 'Comando para tomarla:';
            $lines[] = "```\n{$offer->actionCommand}\n```";
        }

        return implode("\n", $lines);
    }
}
