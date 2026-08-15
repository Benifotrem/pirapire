<?php

namespace App\Services\Mempool;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Reports current Bitcoin mainnet block height and fee estimates for the `/mempool` command. */
class MempoolClient
{
    private readonly string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? config('services.mempool.api_base_url'), '/');
    }

    public function summary(): string
    {
        try {
            $height = Http::timeout(10)->get("{$this->baseUrl}/blocks/tip/height");
            $fees = Http::timeout(10)->get("{$this->baseUrl}/v1/fees/recommended");

            if ($height->failed() || $fees->failed()) {
                throw new \RuntimeException('mempool.space returned a non-2xx response.');
            }

            $feeData = $fees->json();

            return implode("\n", [
                '⛓️ *Estado de la Mempool (Bitcoin mainnet)*',
                "Altura de bloque: {$height->body()}",
                'Tarifas recomendadas (sat/vB):',
                "  🚀 Próximo bloque: {$feeData['fastestFee']}",
                "  ⏱️ ~30 min: {$feeData['halfHourFee']}",
                "  🕐 ~1 hora: {$feeData['hourFee']}",
                "  🐢 Económica: {$feeData['economyFee']}",
            ]);
        } catch (Throwable $e) {
            Log::error('mempool command failed', ['err' => $e->getMessage()]);

            return '⚠️ No se pudo consultar mempool.space en este momento. Intenta de nuevo en unos minutos.';
        }
    }
}
