<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Telegram\WebAppAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates requests from the admin Mini App. Unlike the customer Mini
 * App, this never creates an account — a Telegram chat only grants access
 * if it's already linked to an existing admin/support User (via
 * TelegramLinkController's "/vincular CODE" flow), same rule as
 * StaffTelegramAuthController's OTP login.
 */
class AuthenticateAdminMiniApp
{
    public function handle(Request $request, Closure $next): Response
    {
        $initData = $request->header('X-Telegram-Init-Data', '');
        $botToken = config('services.telegram.bot_token');

        $data = $initData ? WebAppAuth::validate($initData, (string) $botToken) : null;
        $userId = $data['user']['id'] ?? null;

        if (! $userId) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $admin = User::where('telegram_chat_id', (string) $userId)->first();

        if (! $admin || ! in_array($admin->role, ['admin', 'support'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->attributes->set('admin', $admin);

        return $next($request);
    }
}
