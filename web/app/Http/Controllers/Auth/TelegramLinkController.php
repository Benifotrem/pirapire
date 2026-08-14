<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Lets an authenticated admin link their Telegram account for future
 * passwordless login (see StaffTelegramAuthController). Requires the admin
 * to message the bot with a one-time code because Telegram bots can never
 * push the first message to a chat — see TelegramWebhookController, which
 * does the other half of this handshake.
 */
class TelegramLinkController extends Controller
{
    private const CACHE_PREFIX = 'telegram-link:';

    private const CODE_TTL_SECONDS = 600;

    public function show(): View
    {
        $code = strtoupper(Str::random(6));

        Cache::put(self::CACHE_PREFIX.$code, [
            'user_id' => Auth::guard('web')->id(),
            'status' => 'pending',
        ], self::CODE_TTL_SECONDS);

        return view('auth.staff-telegram-link', ['code' => $code]);
    }

    public function status(string $code): JsonResponse
    {
        $entry = Cache::get(self::CACHE_PREFIX.strtoupper($code));

        if (! $entry || $entry['user_id'] !== Auth::guard('web')->id()) {
            return response()->json(['status' => 'expired']);
        }

        return response()->json(['status' => $entry['status']]);
    }
}
