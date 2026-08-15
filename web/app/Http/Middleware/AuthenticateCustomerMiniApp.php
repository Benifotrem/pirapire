<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Services\Telegram\WebAppAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates requests from the customer Mini App using the same
 * telegram_chat_id identity as the bot webhook — opening the Mini App from
 * a private chat with the bot puts the same id in both places, so a
 * customer's data is identical whether they use /commands or the Mini App.
 * Auto-creates the Customer on first contact, same as
 * TelegramCustomerWebhookController.
 */
class AuthenticateCustomerMiniApp
{
    public function handle(Request $request, Closure $next): Response
    {
        $initData = $request->header('X-Telegram-Init-Data', '');
        $botToken = config('services.telegram_customer_bot.bot_token');

        $data = $initData ? WebAppAuth::validate($initData, (string) $botToken) : null;
        $userId = $data['user']['id'] ?? null;

        if (! $userId) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $customer = Customer::firstOrCreate(['telegram_chat_id' => (string) $userId]);
        $request->attributes->set('customer', $customer);

        return $next($request);
    }
}
