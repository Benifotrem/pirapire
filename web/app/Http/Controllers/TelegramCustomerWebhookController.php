<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\Bot\CustomerCommandRouter;
use App\Services\Telegram\CustomerTelegramBotClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Receives updates from the public customer-facing Telegram bot (distinct
 * from TelegramWebhookController, which is the private admin ops bot) —
 * this is the full replacement for the old WhatsApp bot's message handler.
 * /mempool, /vip, /escrow now run as plain PHP via CustomerCommandRouter
 * instead of round-tripping to a separate Node.js process.
 */
class TelegramCustomerWebhookController extends Controller
{
    /** Matches App\Http\Controllers\CustomerTelegramLinkController::CACHE_PREFIX — the two halves of the same handshake. */
    private const LINK_CACHE_PREFIX = 'customer-telegram-link:';

    public function __construct(
        private readonly CustomerCommandRouter $router,
        private readonly CustomerTelegramBotClient $bot,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.telegram_customer_bot.webhook_secret');
        if ($secret && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $secret) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Telegram retries a webhook forever until it gets a 2xx response, so
        // any unexpected failure below (a malformed update shape, a database
        // hiccup, a bug in a command handler) must never bubble up into a
        // 500 — that would trigger a retry storm instead of just dropping
        // this one update. The specific catches inside CustomerCommandRouter
        // already turn known failure modes (invalid escrow state, LNbits
        // being down) into a friendly reply; this is the last-resort net.
        try {
            $chatId = trim((string) $request->input('message.chat.id', ''));
            $text = trim((string) $request->input('message.text', ''));

            if ($chatId === '' || $text === '') {
                return response()->json(['status' => 'ignored']);
            }

            // Handled before the generic firstOrCreate() below on purpose: a
            // customer who already has a wallet-authenticated row (keyed by
            // linking_key, no telegram_chat_id yet — see LnurlAuthController)
            // is attaching *that* row to this chat, not creating a new one.
            if (preg_match('/^\/vincular\s+(\S+)$/i', $text, $matches)) {
                return $this->handleLink($chatId, strtoupper($matches[1]));
            }

            $customer = Customer::firstOrCreate(['telegram_chat_id' => $chatId]);

            $reply = $this->router->route($customer, $text);
            if ($reply === null) {
                return response()->json(['status' => 'ignored']);
            }

            $this->bot->sendMessage($chatId, $reply);

            return response()->json(['status' => 'replied']);
        } catch (RuntimeException $e) {
            return response()->json(['status' => 'send_failed', 'error' => $e->getMessage()]);
        } catch (Throwable $e) {
            Log::error('Unhandled error in customer Telegram webhook', [
                'error' => $e->getMessage(),
                'payload' => $request->input('message'),
            ]);

            return response()->json(['status' => 'error']);
        }
    }

    private function handleLink(string $chatId, string $code): JsonResponse
    {
        $cacheKey = self::LINK_CACHE_PREFIX.$code;
        $entry = Cache::get($cacheKey);

        if (! $entry) {
            $this->bot->sendMessage($chatId, '❌ Ese código no existe o venció. Generá uno nuevo desde tu panel en pirapire.pro/dashboard.');

            return response()->json(['status' => 'unknown_code']);
        }

        $target = Customer::find($entry['customer_id']);
        if (! $target) {
            return response()->json(['status' => 'unknown_customer']);
        }

        if ($target->telegram_chat_id === $chatId) {
            Cache::put($cacheKey, [...$entry, 'status' => 'confirmed'], 300);
            $this->bot->sendMessage($chatId, '✅ Esta cuenta de Telegram ya estaba vinculada a tu cuenta de Pirapire.');

            return response()->json(['status' => 'already_linked']);
        }

        // A different Customer row may already own this chat_id — most
        // often an empty one firstOrCreate() minted the first time this
        // chat messaged the bot (e.g. an earlier /start with no code). If
        // it has no real activity, it's safe to fold into the target;
        // otherwise refuse rather than silently orphaning someone's alerts
        // or escrow jobs.
        $existing = Customer::where('telegram_chat_id', $chatId)->first();
        if ($existing && $existing->id !== $target->id) {
            $hasActivity = $existing->alerts()->exists()
                || $existing->escrowJobsCreated()->exists()
                || $existing->escrowJobsAsFreelancer()->exists();

            if ($hasActivity) {
                $this->bot->sendMessage($chatId, '❌ Este chat de Telegram ya tiene actividad en Pirapire asociada a otra cuenta. Escribinos para resolverlo a mano.');

                return response()->json(['status' => 'conflict']);
            }

            $existing->delete();
        }

        $target->forceFill(['telegram_chat_id' => $chatId])->save();
        Cache::put($cacheKey, [...$entry, 'status' => 'confirmed'], 300);

        $this->bot->sendMessage($chatId, '✅ Tu cuenta de Telegram quedó vinculada a Pirapire. Ya podés cerrar esta pantalla y volver al navegador.');

        return response()->json(['status' => 'linked']);
    }
}
