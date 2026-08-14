<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Telegram\TelegramBotClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

/**
 * Telegram one-time-code login for the Filament admin panel — a third
 * passwordless option alongside the wallet (StaffLnurlAuthController) and
 * WhatsApp (StaffWhatsappAuthController) logins. Never auto-creates a User
 * (same reasoning as those two): a Telegram chat only works here once
 * explicitly linked to an existing admin/support account via
 * TelegramLinkController.
 *
 * Unlike the wallet/WhatsApp flows, there's no "link mode" branch on this
 * controller — linking a fresh Telegram chat requires the admin to message
 * the bot first (Telegram bots can't push a first message), so that half
 * lives in TelegramLinkController + TelegramWebhookController instead.
 */
class StaffTelegramAuthController extends Controller
{
    private const CACHE_PREFIX = 'staff-telegram-auth:';

    private const CODE_TTL_SECONDS = 300;

    private const MAX_ATTEMPTS = 5;

    private const SESSION_TOKEN_KEY = 'staff_telegram_auth_token';

    public function __construct(private readonly TelegramBotClient $bot) {}

    public function showRequest(): View
    {
        return view('auth.staff-telegram-login');
    }

    public function request(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $validated['email'])
            ->whereIn('role', ['admin', 'support'])
            ->whereNotNull('telegram_chat_id')
            ->first();

        if (! $user) {
            return back()->withErrors(['email' => 'No hay ninguna cuenta de administrador con Telegram vinculado para ese email.']);
        }

        $code = (string) random_int(100000, 999999);
        $token = Str::random(32);

        Cache::put(self::CACHE_PREFIX.$token, [
            'code' => $code,
            'user_id' => $user->id,
            'attempts' => 0,
        ], self::CODE_TTL_SECONDS);

        Session::put(self::SESSION_TOKEN_KEY, $token);

        try {
            $this->bot->sendMessage($user->telegram_chat_id, "🔐 Tu código de acceso a Pirapire Admin es: {$code}\nVence en 5 minutos.");
        } catch (RuntimeException $e) {
            Cache::forget(self::CACHE_PREFIX.$token);
            Session::forget(self::SESSION_TOKEN_KEY);

            return back()->withErrors(['email' => $e->getMessage()]);
        }

        return redirect()->route('staff-telegram-auth.verify-form');
    }

    public function showVerify(): View|RedirectResponse
    {
        $token = Session::get(self::SESSION_TOKEN_KEY);

        if (! $token || ! Cache::has(self::CACHE_PREFIX.$token)) {
            return redirect()->route('staff-login-telegram')->withErrors([
                'email' => 'El código expiró. Pedí uno nuevo.',
            ]);
        }

        return view('auth.staff-telegram-verify');
    }

    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $token = Session::get(self::SESSION_TOKEN_KEY);
        $entry = $token ? Cache::get(self::CACHE_PREFIX.$token) : null;

        if (! $entry) {
            return redirect()->route('staff-login-telegram')->withErrors([
                'email' => 'El código expiró. Pedí uno nuevo.',
            ]);
        }

        if (! hash_equals($entry['code'], $validated['code'])) {
            $entry['attempts']++;

            if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
                Cache::forget(self::CACHE_PREFIX.$token);
                Session::forget(self::SESSION_TOKEN_KEY);

                return redirect()->route('staff-login-telegram')->withErrors([
                    'email' => 'Demasiados intentos fallidos. Pedí un código nuevo.',
                ]);
            }

            Cache::put(self::CACHE_PREFIX.$token, $entry, self::CODE_TTL_SECONDS);

            return back()->withErrors(['code' => 'Código incorrecto.']);
        }

        Cache::forget(self::CACHE_PREFIX.$token);
        Session::forget(self::SESSION_TOKEN_KEY);

        $user = User::findOrFail($entry['user_id']);

        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();

        return redirect('/admin');
    }
}
