<?php

namespace App\Console\Commands;

use App\DTOs\NormalizedP2POffer;
use App\Jobs\SendP2POfferAlert;
use App\Models\Alert;
use App\Services\P2P\AlertMatcher;
use App\Services\P2P\P2PMessageFormatter;
use App\Services\P2P\P2POfferAggregator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Polls every configured P2P source (see App\Services\P2P\P2POfferAggregator
 * and its drivers under App\Services\P2P\Drivers) and fans out matching
 * alerts to subscribers — replaces the RoboSats-only `robosats:poll`
 * command now that Mostro is a second source. "New since last poll" is
 * tracked per source+currency as a seen-IDs set in cache (not a
 * max-seen-id, unlike the old RoboSats-only version — Mostro's IDs are
 * Nostr event hashes, not orderable integers), so this needs to survive
 * between separate command invocations (the scheduler runs this as a
 * fresh process every minute).
 */
class PollP2POffers extends Command
{
    protected $signature = 'p2p:poll';

    protected $description = 'Poll every configured P2P source (RoboSats, Mostro) and fan out matching alerts to subscribers.';

    private const CURRENCIES = ['PYG', 'USD'];

    public function handle(P2POfferAggregator $aggregator, P2PMessageFormatter $formatter): int
    {
        if (! config('services.robosats.api_base_url') && empty(config('services.mostro.relays'))) {
            $this->warn('Ninguna fuente P2P está configurada (ROBOSATS_API_BASE_URL / MOSTRO_RELAYS) — nada que sondear. Ver README "Alertas P2P de RoboSats y Mostro".');

            return self::SUCCESS;
        }

        foreach (self::CURRENCIES as $currency) {
            $this->pollCurrency($aggregator, $formatter, $currency);
        }

        return self::SUCCESS;
    }

    private function pollCurrency(P2POfferAggregator $aggregator, P2PMessageFormatter $formatter, string $currency): void
    {
        $offers = $aggregator->collect($currency);
        if (empty($offers)) {
            return;
        }

        $bySource = [];
        foreach ($offers as $offer) {
            $bySource[$offer->source][] = $offer;
        }

        $newOffers = [];
        foreach ($bySource as $source => $sourceOffers) {
            $newOffers = [...$newOffers, ...$this->diffAgainstSeen($source, $currency, $sourceOffers)];
        }

        if (empty($newOffers)) {
            return;
        }

        $subscribers = Alert::query()
            ->where('currency', $currency)
            ->where('is_active', true)
            ->whereHas('customer', fn ($q) => $q->whereNotNull('telegram_chat_id'))
            ->with('customer')
            ->get();

        $dispatched = 0;
        foreach ($newOffers as $offer) {
            $message = $formatter->format($offer);

            foreach ($subscribers as $alert) {
                if (AlertMatcher::matches($alert, $offer)) {
                    $this->dispatchAlert($alert, $message);
                    $dispatched++;
                }
            }
        }

        $this->info("P2P [{$currency}]: ".count($newOffers).' oferta(s) nueva(s), '.$dispatched.' notificación(es) despachada(s) a '.$subscribers->count().' suscriptores activos.');
    }

    /**
     * @param  array<int, NormalizedP2POffer>  $offers
     * @return array<int, NormalizedP2POffer>
     */
    private function diffAgainstSeen(string $source, string $currency, array $offers): array
    {
        $cacheKey = "p2p:seen-offer-ids:{$source}:{$currency}";
        $seen = Cache::get($cacheKey);

        Cache::forever($cacheKey, array_map(fn ($offer) => $offer->id, $offers));

        // First time we've ever seen this source+currency: establish a
        // baseline instead of alerting for every offer already listed.
        if ($seen === null) {
            return [];
        }

        $seenIds = array_flip($seen);

        return array_values(array_filter($offers, fn ($offer) => ! isset($seenIds[$offer->id])));
    }

    private function dispatchAlert(Alert $alert, string $message): void
    {
        $chatId = $alert->customer->telegram_chat_id;

        if ($alert->customer->isVip()) {
            SendP2POfferAlert::dispatch($chatId, $message);

            return;
        }

        $delayMinutes = (int) config('services.alerts.free_tier_delay_minutes');
        SendP2POfferAlert::dispatch($chatId, $message)->delay(now()->addMinutes($delayMinutes));
    }
}
