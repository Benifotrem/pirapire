<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Telegram\TelegramBotClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Receives Telegram Bot API updates (https://core.telegram.org/bots/api#update)
 * for the sole purpose of learning an admin's chat_id — a bot can never push
 * the first message to a chat, so linking requires the admin to message the
 * bot first with a one-time code minted by TelegramLinkController. Once
 * linked, ordinary login (StaffTelegramAuthController) only ever sends
 * outbound and never touches this endpoint.
 *
 * Authenticated by the `X-Telegram-Bot-Api-Secret-Token` header Telegram
 * echoes back on every update once configured via `setWebhook` — see
 * README "Panel de administración" for the one-time setup command.
 */
class TelegramWebhookController extends Controller
{
    private const CACHE_PREFIX = 'telegram-link:';

    public function __construct(private readonly TelegramBotClient $bot) {}

    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.telegram.webhook_secret');
        if ($secret && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $secret) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $chatId = (string) $request->input('message.chat.id', '');
        $text = trim((string) $request->input('message.text', ''));

        // Telegram expects 200 OK for anything it sends, including updates
        // we don't care about (edited messages, non-text content, etc.) —
        // returning an error would make Telegram keep retrying forever.
        if ($chatId === '' || $text === '') {
            return response()->json(['status' => 'ignored']);
        }

        if (! preg_match('/^\/vincular\s+(\S+)$/i', $text, $matches)) {
            if (str_starts_with($text, '/start')) {
                $this->reply($chatId, 'Para vincular esta cuenta de Telegram al panel de administración de Pirapire, entrá a /admin, abrí el menú de usuario y elegí "Vincular Telegram 📨" — ahí te va a dar un código para mandarme.');
            }

            return response()->json(['status' => 'ignored']);
        }

        $code = strtoupper($matches[1]);
        $cacheKey = self::CACHE_PREFIX.$code;
        $entry = Cache::get($cacheKey);

        if (! $entry) {
            $this->reply($chatId, '❌ Ese código no existe o venció. Generá uno nuevo desde el panel.');

            return response()->json(['status' => 'unknown_code']);
        }

        $conflict = User::where('telegram_chat_id', $chatId)
            ->where('id', '!=', $entry['user_id'])
            ->exists();

        if ($conflict) {
            $this->reply($chatId, '❌ Este chat de Telegram ya está vinculado a otra cuenta de administrador.');

            return response()->json(['status' => 'conflict']);
        }

        $user = User::find($entry['user_id']);
        if (! $user) {
            return response()->json(['status' => 'unknown_user']);
        }

        $user->forceFill(['telegram_chat_id' => $chatId])->save();
        Cache::put($cacheKey, ['user_id' => $entry['user_id'], 'status' => 'confirmed'], 300);

        $this->reply($chatId, '✅ Tu cuenta de Telegram quedó vinculada al panel de administración de Pirapire. Ya podés cerrar esta pantalla y volver al navegador.');

        return response()->json(['status' => 'linked']);
    }

    private function reply(string $chatId, string $text): void
    {
        try {
            $this->bot->sendMessage($chatId, $text);
        } catch (RuntimeException) {
            // Best-effort: the link itself already succeeded/failed on the
            // Laravel side regardless of whether this confirmation lands.
        }
    }
}
