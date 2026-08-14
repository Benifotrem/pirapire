<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Talks directly to the Telegram Bot API (https://api.telegram.org) to
 * deliver admin login codes — unlike WhatsappBotClient, this has no
 * dependency on the Node.js bot process or any paired session being up,
 * since Telegram's Bot API is a plain HTTPS endpoint Laravel can call on
 * its own. Uses the same bot/token as whatsapp-bot's TelegramNotifier
 * (health-check alerts); TELEGRAM_ADMIN_BOT_TOKEN must match in both
 * web/.env and whatsapp-bot/.env.
 */
class TelegramBotClient
{
    private const API_BASE = 'https://api.telegram.org';

    private readonly ?string $token;

    public function __construct(?string $token = null)
    {
        $this->token = $token ?? config('services.telegram.bot_token');
    }

    public function sendMessage(string $chatId, string $text): void
    {
        if (! $this->token) {
            throw new RuntimeException('TELEGRAM_ADMIN_BOT_TOKEN is not configured.');
        }

        $response = Http::timeout(10)->post(self::API_BASE."/bot{$this->token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
        ]);

        if ($response->failed()) {
            Log::error('Telegram sendMessage failed', ['chat_id' => $chatId, 'body' => $response->body()]);
            throw new RuntimeException('No se pudo enviar el mensaje de Telegram.');
        }
    }
}
