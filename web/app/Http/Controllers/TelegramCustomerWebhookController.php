<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\Bot\CustomerCommandRouter;
use App\Services\Telegram\CustomerTelegramBotClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
}
