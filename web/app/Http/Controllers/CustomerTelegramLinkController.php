<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Lets a customer already authenticated via LNURL-auth (see LnurlAuthController)
 * link their Telegram chat to that same Customer row, so alerts/escrow
 * notifications reach them and the dashboard's "Telegram vinculado" status
 * reflects reality. Requires the customer to message the bot with a
 * one-time code because Telegram bots can never push the first message to
 * a chat — see TelegramCustomerWebhookController, which does the other half
 * of this handshake. Mirrors App\Http\Controllers\Auth\TelegramLinkController,
 * the same flow already built for admin/staff accounts.
 *
 * Without this, a customer who logs in with a wallet and then messages
 * /start to the bot ends up with two disconnected Customer rows — one
 * keyed by linking_key (what the dashboard reads), one keyed by
 * telegram_chat_id (what the bot created) — and the dashboard never stops
 * saying "Telegram not linked" no matter how many times they message the bot.
 */
class CustomerTelegramLinkController extends Controller
{
    private const CACHE_PREFIX = 'customer-telegram-link:';

    private const CODE_TTL_SECONDS = 600;

    public function show(): View
    {
        $code = strtoupper(Str::random(6));

        Cache::put(self::CACHE_PREFIX.$code, [
            'customer_id' => Auth::guard('customer')->id(),
            'status' => 'pending',
        ], self::CODE_TTL_SECONDS);

        return view('customer.telegram-link', ['code' => $code]);
    }

    public function status(string $code): JsonResponse
    {
        $entry = Cache::get(self::CACHE_PREFIX.strtoupper($code));

        if (! $entry || $entry['customer_id'] !== Auth::guard('customer')->id()) {
            return response()->json(['status' => 'expired']);
        }

        return response()->json(['status' => $entry['status']]);
    }
}
