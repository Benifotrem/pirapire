<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\Bot\CustomerCommandRouter;
use App\Services\Telegram\CustomerTelegramBotClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

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

        $chatId = (string) $request->input('message.chat.id', '');
        $text = trim((string) $request->input('message.text', ''));

        if ($chatId === '' || $text === '') {
            return response()->json(['status' => 'ignored']);
        }

        $customer = Customer::firstOrCreate(['telegram_chat_id' => $chatId]);

        $reply = $this->router->route($customer, $text);
        if ($reply === null) {
            return response()->json(['status' => 'ignored']);
        }

        try {
            $this->bot->sendMessage($chatId, $reply);
        } catch (RuntimeException $e) {
            return response()->json(['status' => 'send_failed', 'error' => $e->getMessage()]);
        }

        return response()->json(['status' => 'replied']);
    }
}
