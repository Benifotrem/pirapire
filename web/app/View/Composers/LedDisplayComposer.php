<?php

namespace App\View\Composers;

use App\Models\EscrowJob;
use App\Models\LedAd;
use App\Models\LedDisplaySetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Feeds the header's LED ticker (resources/views/components/led-display.blade.php)
 * on every page render — cached briefly since it's on every request, same
 * pattern as the admin dashboard widgets. Open escrow jobs are mixed in
 * alongside the sponsored ads (see openJobAds()) so the ticker doubles as
 * a public teaser for the job board, without requiring a login to see that
 * work is available.
 */
class LedDisplayComposer
{
    public function compose(View $view): void
    {
        $data = Cache::remember('led-display:data', 30, function () {
            $setting = LedDisplaySetting::current();

            $ads = LedAd::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['message', 'url'])
                ->map(fn (LedAd $ad) => ['message' => $ad->message, 'url' => $ad->url, 'target' => '_blank'])
                ->all();

            return [
                'enabled' => $setting->enabled,
                'color' => $setting->color,
                'ads' => [...$ads, ...$this->openJobAds()],
            ];
        });

        $view->with('ledDisplay', $data);
    }

    /**
     * Every item points at the same /dashboard/escrow URL — auth:customer
     * on that route does the "logged in -> straight to the board, guest ->
     * invited to log in first" branching on its own (LnurlAuthController's
     * post-login redirect honors the intended URL the guest middleware
     * stashes), so no client-side auth check is needed here.
     */
    private function openJobAds(): array
    {
        return EscrowJob::where('status', 'open')
            ->latest()
            ->limit(15)
            ->get(['description', 'amount_sats'])
            ->map(fn (EscrowJob $job) => [
                'message' => sprintf(
                    '💼 %s — ganás %s sats si lo aceptás',
                    Str::limit($job->description, 80),
                    number_format($job->amount_sats),
                ),
                'url' => route('escrow.board'),
                'target' => '_self',
            ])
            ->all();
    }
}
