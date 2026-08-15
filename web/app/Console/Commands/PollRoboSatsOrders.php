<?php

namespace App\Console\Commands;

use App\Jobs\SendRoboSatsAlert;
use App\Models\Alert;
use App\Services\RoboSats\AlertMatcher;
use App\Services\RoboSats\RoboSatsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Polls the RoboSats public order book (see routes/console.php for the
 * schedule) and fans out matching alerts — the full replacement for the
 * old WhatsApp bot's Node-based RoboSatsPoller. Order IDs are monotonically
 * increasing (RoboSats convention), so "new since last poll" is tracked as
 * a per-currency max-seen-id in cache rather than an in-memory Set — this
 * needs to survive between separate command invocations (the scheduler
 * runs this as a fresh process every minute), which an in-memory Set
 * couldn't.
 */
class PollRoboSatsOrders extends Command
{
    protected $signature = 'robosats:poll';

    protected $description = 'Poll the RoboSats order book and fan out matching alerts to subscribers.';

    private const CURRENCIES = ['PYG', 'USD'];

    public function handle(RoboSatsClient $robosats): int
    {
        if (! $robosats->isConfigured()) {
            $this->warn('ROBOSATS_API_BASE_URL no está configurado — nada que sondear. Ver README "Alertas P2P de RoboSats".');

            return self::SUCCESS;
        }

        foreach (self::CURRENCIES as $currency) {
            $this->pollCurrency($robosats, $currency);
        }

        return self::SUCCESS;
    }

    private function pollCurrency(RoboSatsClient $robosats, string $currency): void
    {
        $orders = $robosats->fetchBook($currency);
        if (empty($orders)) {
            return;
        }

        $cacheKey = "robosats:max-seen-order-id:{$currency}";
        $maxOrderId = (int) max(array_column($orders, 'id'));

        // First time we've ever seen this currency's book: establish a
        // baseline instead of alerting for every order already listed.
        if (! Cache::has($cacheKey)) {
            Cache::forever($cacheKey, $maxOrderId);

            return;
        }

        $maxSeenId = (int) Cache::get($cacheKey);
        $newOrders = array_filter($orders, fn (array $order) => ($order['id'] ?? 0) > $maxSeenId);

        Cache::forever($cacheKey, $maxOrderId);

        if (empty($newOrders)) {
            return;
        }

        $subscribers = Alert::query()
            ->where('currency', $currency)
            ->where('is_active', true)
            ->whereHas('customer', fn ($q) => $q->whereNotNull('telegram_chat_id'))
            ->with('customer')
            ->get();

        foreach ($newOrders as $order) {
            $message = AlertMatcher::formatOrderMessage($order, $currency);

            foreach ($subscribers as $alert) {
                if (AlertMatcher::matches($alert, $order)) {
                    $this->dispatchAlert($alert, $message);
                }
            }
        }

        $this->info("RoboSats [{$currency}]: ".count($newOrders).' orden(es) nueva(s), '.$subscribers->count().' suscriptores activos.');
    }

    private function dispatchAlert(Alert $alert, string $message): void
    {
        $chatId = $alert->customer->telegram_chat_id;

        if ($alert->customer->isVip()) {
            SendRoboSatsAlert::dispatch($chatId, $message);

            return;
        }

        $delayMinutes = (int) config('services.alerts.free_tier_delay_minutes');
        SendRoboSatsAlert::dispatch($chatId, $message)->delay(now()->addMinutes($delayMinutes));
    }
}
