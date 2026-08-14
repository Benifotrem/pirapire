<?php

namespace App\Services\Whatsapp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Talks to the Node.js WhatsApp bot's internal-only HTTP API (see
 * whatsapp-bot/src/server/internalApi.ts) to push a message through the
 * bot's paired WhatsApp session — used to deliver admin login codes
 * (App\Http\Controllers\Auth\StaffWhatsappAuthController). This is the
 * reverse direction of routes/api.php, which the bot calls into Laravel.
 */
class WhatsappBotClient
{
    private readonly string $baseUrl;

    private readonly ?string $token;

    public function __construct(?string $baseUrl = null, ?string $token = null)
    {
        $this->baseUrl = rtrim($baseUrl ?? config('services.whatsapp_bot.internal_url'), '/');
        $this->token = $token ?? config('services.whatsapp_bot.internal_token');
    }

    /** @param string $to WhatsApp JID, e.g. "595981111111@s.whatsapp.net" */
    public function sendMessage(string $to, string $message): void
    {
        $response = Http::withToken($this->token)
            ->timeout(10)
            ->post($this->baseUrl.'/send-message', [
                'to' => $to,
                'message' => $message,
            ]);

        if ($response->failed()) {
            Log::error('WhatsApp bot sendMessage failed', ['to' => $to, 'body' => $response->body()]);
            throw new RuntimeException('No se pudo enviar el mensaje de WhatsApp. ¿El bot está conectado?');
        }
    }
}
