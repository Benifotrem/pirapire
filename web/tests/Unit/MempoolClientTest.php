<?php

namespace Tests\Unit;

use App\Services\Mempool\MempoolClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MempoolClientTest extends TestCase
{
    public function test_summary_reports_height_and_fees(): void
    {
        Http::fake([
            'mempool.test/blocks/tip/height' => Http::response('850000', 200),
            'mempool.test/v1/fees/recommended' => Http::response([
                'fastestFee' => 20,
                'halfHourFee' => 15,
                'hourFee' => 10,
                'economyFee' => 5,
                'minimumFee' => 1,
            ], 200),
        ]);

        $summary = (new MempoolClient('http://mempool.test'))->summary();

        $this->assertStringContainsString('850000', $summary);
        $this->assertStringContainsString('20', $summary);
    }

    public function test_summary_degrades_gracefully_on_failure(): void
    {
        Http::fake([
            'mempool.test/*' => Http::response(null, 500),
        ]);

        $summary = (new MempoolClient('http://mempool.test'))->summary();

        $this->assertStringContainsString('No se pudo consultar', $summary);
    }
}
