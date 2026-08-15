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

    /**
     * $parseMode is opt-in (null by default) rather than always-on so
     * existing plain-text callers aren't suddenly subject to Telegram's
     * Markdown escaping rules for characters like `_`, `*`, `[` — pass
     * 'Markdown' explicitly (as App\Jobs\SendP2POfferAlert does) when the
     * message actually uses Markdown syntax (links, code blocks, bold).
     */
    public function sendMessage(string $chatId, string $text, ?string $parseMode = null): void
    {
        if (! $this->token) {
            throw new RuntimeException('TELEGRAM_ADMIN_BOT_TOKEN is not configured.');
        }

        $payload = ['chat_id' => $chatId, 'text' => $text];
        if ($parseMode !== null) {
            $payload['parse_mode'] = $parseMode;
        }

        $response = Http::timeout(10)->post(self::API_BASE."/bot{$this->token}/sendMessage", $payload);

        if ($response->failed()) {
            Log::error('Telegram sendMessage failed', ['chat_id' => $chatId, 'body' => $response->body()]);
            throw new RuntimeException('No se pudo enviar el mensaje de Telegram.');
        }
    }
}
