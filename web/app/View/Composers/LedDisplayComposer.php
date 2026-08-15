<?php

namespace App\View\Composers;

use App\Models\LedAd;
use App\Models\LedDisplaySetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Feeds the header's LED ticker (resources/views/components/led-display.blade.php)
 * on every page render — cached briefly since it's on every request, same
 * pattern as the admin dashboard widgets.
 */
class LedDisplayComposer
{
    public function compose(View $view): void
    {
        $data = Cache::remember('led-display:data', 30, function () {
            $setting = LedDisplaySetting::current();

            return [
                'enabled' => $setting->enabled,
                'color' => $setting->color,
                'ads' => LedAd::where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['message', 'url'])
                    ->toArray(),
            ];
        });

        $view->with('ledDisplay', $data);
    }
}
