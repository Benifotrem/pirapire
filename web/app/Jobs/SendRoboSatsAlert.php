<?php

namespace App\Jobs;

use App\Services\Telegram\CustomerTelegramBotClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers a single RoboSats order alert to one subscriber. Dispatched with
 * no delay for VIP subscribers, and with a FREE_TIER_DELAY_MINUTES delay
 * for the free tier (App\Console\Commands\PollRoboSatsOrders) — the queued
 * `queue` container already running via docker-compose handles the delay,
 * replacing the old Node bot's BullMQ-backed delayed queue.
 */
class SendRoboSatsAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $telegramChatId,
        public readonly string $message,
    ) {}

    public function handle(CustomerTelegramBotClient $bot): void
    {
        $bot->sendMessage($this->telegramChatId, $this->message);
    }
}
