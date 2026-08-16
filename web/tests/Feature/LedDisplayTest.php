<?php

namespace Tests\Feature;

use App\Models\EscrowJob;
use App\Models\LedAd;
use App\Models\LedDisplaySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LedDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('led-display:data');
    }

    public function test_hidden_when_there_are_no_active_ads(): void
    {
        $this->get('/')->assertOk()->assertDontSee('id="led-display"', false);
    }

    public function test_hidden_when_disabled_even_with_active_ads(): void
    {
        LedDisplaySetting::current()->update(['enabled' => false]);
        LedAd::factory()->create(['is_active' => true]);

        $this->get('/')->assertOk()->assertDontSee('id="led-display"', false);
    }

    public function test_shows_only_active_ads_ordered_by_sort_order(): void
    {
        LedAd::factory()->create(['message' => 'Segundo', 'sort_order' => 2, 'is_active' => true]);
        LedAd::factory()->create(['message' => 'Primero', 'sort_order' => 1, 'is_active' => true]);
        LedAd::factory()->create(['message' => 'Oculto', 'sort_order' => 0, 'is_active' => false]);

        $response = $this->get('/')->assertOk();

        $response->assertSee('id="led-display"', false);
        $response->assertDontSee('Oculto');

        $html = $response->getContent();
        $this->assertLessThan(
            strpos($html, 'Segundo'),
            strpos($html, 'Primero'),
            'Expected "Primero" (sort_order 1) to render before "Segundo" (sort_order 2).',
        );
    }

    public function test_reflects_the_configured_color(): void
    {
        LedDisplaySetting::current()->update(['color' => 'blue']);
        LedAd::factory()->create(['is_active' => true]);

        $this->get('/')->assertOk()->assertSee('data-mode="blue"', false);
    }

    public function test_clicking_target_is_the_ad_url(): void
    {
        LedAd::factory()->create(['url' => 'https://example.com/promo', 'is_active' => true]);

        $this->get('/')->assertOk()->assertSee('https:\/\/example.com\/promo', false);
    }

    public function test_open_escrow_jobs_appear_in_the_ticker(): void
    {
        EscrowJob::factory()->create(['status' => 'open', 'counterparty_customer_id' => null, 'description' => 'Traducir un sitio web', 'amount_sats' => 5000]);

        $response = $this->get('/')->assertOk();

        $response->assertSee('id="led-display"', false);
        $response->assertSee('Traducir un sitio web');
        $response->assertSee('5,000 sats');
    }

    public function test_job_ads_are_hidden_when_the_ticker_is_disabled(): void
    {
        LedDisplaySetting::current()->update(['enabled' => false]);
        EscrowJob::factory()->create(['status' => 'open', 'counterparty_customer_id' => null]);

        $this->get('/')->assertOk()->assertDontSee('id="led-display"', false);
    }

    public function test_a_job_ad_links_to_the_escrow_board_and_opens_in_the_same_tab(): void
    {
        EscrowJob::factory()->create(['status' => 'open', 'counterparty_customer_id' => null]);

        $response = $this->get('/')->assertOk();

        $response->assertSee(str_replace('/', '\/', route('escrow.board')), false);
        $response->assertSee('_self', false);
    }

    public function test_non_open_jobs_do_not_appear_in_the_ticker(): void
    {
        EscrowJob::factory()->create(['status' => 'completed']);

        $this->get('/')->assertOk()->assertDontSee('id="led-display"', false);
    }
}
