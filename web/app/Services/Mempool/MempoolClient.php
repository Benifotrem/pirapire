<?php

namespace App\Services\Mempool;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Reports current Bitcoin mainnet block height and fee estimates for `/mempool` and the customer Mini App. */
class MempoolClient
{
    private readonly string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? config('services.mempool.api_base_url'), '/');
    }

    public function summary(): string
    {
        $stats = $this->stats();

        if ($stats === null) {
            return '⚠️ No se pudo consultar mempool.space en este momento. Intenta de nuevo en unos minutos.';
        }

        return implode("\n", [
            '⛓️ *Estado de la Mempool (Bitcoin mainnet)*',
            "Altura de bloque: {$stats['height']}",
            'Tarifas recomendadas (sat/vB):',
            "  🚀 Próximo bloque: {$stats['fees']['fastestFee']}",
            "  ⏱️ ~30 min: {$stats['fees']['halfHourFee']}",
            "  🕐 ~1 hora: {$stats['fees']['hourFee']}",
            "  🐢 Económica: {$stats['fees']['economyFee']}",
        ]);
    }

    /** @return array{height: int, fees: array<string, int>}|null */
    public function stats(): ?array
    {
        try {
            $height = Http::timeout(10)->get("{$this->baseUrl}/blocks/tip/height");
            $fees = Http::timeout(10)->get("{$this->baseUrl}/v1/fees/recommended");

            if ($height->failed() || $fees->failed()) {
                throw new \RuntimeException('mempool.space returned a non-2xx response.');
            }

            return [
                'height' => (int) $height->body(),
                'fees' => $fees->json(),
            ];
        } catch (Throwable $e) {
            Log::error('mempool stats fetch failed', ['err' => $e->getMessage()]);

            return null;
        }
    }
}
