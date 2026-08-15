<?php

namespace Tests\Unit;

use App\Models\LedDisplaySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedDisplaySettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_creates_sensible_defaults_on_first_call(): void
    {
        $setting = LedDisplaySetting::current();

        $this->assertTrue($setting->enabled);
        $this->assertSame('red', $setting->color);
        $this->assertDatabaseCount('led_display_settings', 1);
    }

    public function test_current_always_returns_the_same_singleton_row(): void
    {
        $first = LedDisplaySetting::current();
        $first->update(['color' => 'blue']);

        $second = LedDisplaySetting::current();

        $this->assertSame($first->id, $second->id);
        $this->assertSame('blue', $second->color);
        $this->assertDatabaseCount('led_display_settings', 1);
    }
}
