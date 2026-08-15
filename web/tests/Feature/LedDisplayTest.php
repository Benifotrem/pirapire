<?php

namespace Tests\Feature;

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
}
