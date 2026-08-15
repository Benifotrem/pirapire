<?php

namespace App\Jobs;

use App\Services\Telegram\CustomerTelegramBotClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers a single P2P offer alert (RoboSats or Mostro — see
 * App\Services\P2P\P2POfferAggregator) to one subscriber. Dispatched with
 * no delay for VIP subscribers, and with a FREE_TIER_DELAY_MINUTES delay
 * for the free tier (App\Console\Commands\PollP2POffers) — the queued
 * `queue` container already running via docker-compose handles the delay.
 * Sent with Markdown parse mode since App\Services\P2P\P2PMessageFormatter
 * uses it for the offer link and, for Mostro, the copyable mostro-cli
 * command block.
 */
class SendP2POfferAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $telegramChatId,
        public readonly string $message,
    ) {}

    public function handle(CustomerTelegramBotClient $bot): void
    {
        $bot->sendMessage($this->telegramChatId, $this->message, parseMode: 'Markdown');
    }
}
