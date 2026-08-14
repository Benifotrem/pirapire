<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Whatsapp\WhatsappBotClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

/**
 * WhatsApp one-time-code login for the Filament admin panel — a second
 * passwordless option alongside StaffLnurlAuthController's wallet login.
 * Same "login vs. link" duality: an already-authenticated admin requesting
 * a code links that number to their own account; a guest logging in must
 * already have a number linked to an admin/support User (never
 * auto-created — see StaffLnurlAuthController for why).
 */
class StaffWhatsappAuthController extends Controller
{
    private const CACHE_PREFIX = 'staff-whatsapp-auth:';

    private const CODE_TTL_SECONDS = 300;

    private const MAX_ATTEMPTS = 5;

    private const SESSION_TOKEN_KEY = 'staff_whatsapp_auth_token';

    public function __construct(private readonly WhatsappBotClient $bot) {}

    public function showRequest(): View
    {
        return view('auth.staff-whatsapp-login', [
            'linking' => Auth::guard('web')->check(),
        ]);
    }

    public function request(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'whatsapp_number' => 'required|string|min:6|max:20',
        ]);

        $jid = $this->normalizeJid($validated['whatsapp_number']);
        $linking = Auth::guard('web')->check();

        if ($linking) {
            $currentUser = Auth::guard('web')->user();

            if (User::where('whatsapp_number', $jid)->where('id', '!=', $currentUser->id)->exists()) {
                return back()->withErrors(['whatsapp_number' => 'Ese número ya está vinculado a otra cuenta de administrador.']);
            }

            $userId = $currentUser->id;
        } else {
            $user = User::where('whatsapp_number', $jid)->whereIn('role', ['admin', 'support'])->first();

            if (! $user) {
                return back()->withErrors(['whatsapp_number' => 'Ese número no está vinculado a ninguna cuenta de administrador.']);
            }

            $userId = $user->id;
        }

        $code = (string) random_int(100000, 999999);
        $token = Str::random(32);

        Cache::put(self::CACHE_PREFIX.$token, [
            'jid' => $jid,
            'code' => $code,
            'mode' => $linking ? 'link' : 'login',
            'user_id' => $userId,
            'attempts' => 0,
        ], self::CODE_TTL_SECONDS);

        Session::put(self::SESSION_TOKEN_KEY, $token);

        try {
            $this->bot->sendMessage($jid, "🔐 Tu código de acceso a Pirapire Admin es: {$code}\nVence en 5 minutos.");
        } catch (RuntimeException $e) {
            Cache::forget(self::CACHE_PREFIX.$token);
            Session::forget(self::SESSION_TOKEN_KEY);

            return back()->withErrors(['whatsapp_number' => $e->getMessage()]);
        }

        return redirect()->route('staff-whatsapp-auth.verify-form');
    }

    public function showVerify(): View|RedirectResponse
    {
        $token = Session::get(self::SESSION_TOKEN_KEY);

        if (! $token || ! Cache::has(self::CACHE_PREFIX.$token)) {
            return redirect()->route('staff-login-whatsapp')->withErrors([
                'whatsapp_number' => 'El código expiró. Pedí uno nuevo.',
            ]);
        }

        return view('auth.staff-whatsapp-verify');
    }

    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $token = Session::get(self::SESSION_TOKEN_KEY);
        $entry = $token ? Cache::get(self::CACHE_PREFIX.$token) : null;

        if (! $entry) {
            return redirect()->route('staff-login-whatsapp')->withErrors([
                'whatsapp_number' => 'El código expiró. Pedí uno nuevo.',
            ]);
        }

        if (! hash_equals($entry['code'], $validated['code'])) {
            $entry['attempts']++;

            if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
                Cache::forget(self::CACHE_PREFIX.$token);
                Session::forget(self::SESSION_TOKEN_KEY);

                return redirect()->route('staff-login-whatsapp')->withErrors([
                    'whatsapp_number' => 'Demasiados intentos fallidos. Pedí un código nuevo.',
                ]);
            }

            Cache::put(self::CACHE_PREFIX.$token, $entry, self::CODE_TTL_SECONDS);

            return back()->withErrors(['code' => 'Código incorrecto.']);
        }

        Cache::forget(self::CACHE_PREFIX.$token);
        Session::forget(self::SESSION_TOKEN_KEY);

        $user = User::findOrFail($entry['user_id']);

        if ($entry['mode'] === 'link') {
            $user->forceFill(['whatsapp_number' => $entry['jid']])->save();

            return redirect('/admin')->with('status', 'WhatsApp vinculado. Ya podés iniciar sesión con un código.');
        }

        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();

        return redirect('/admin');
    }

    /** Normalizes free-form phone input ("+595 981 111 111", "0981-111111"...) to a WhatsApp JID. */
    private function normalizeJid(string $rawNumber): string
    {
        $digits = preg_replace('/\D+/', '', $rawNumber);

        return $digits.'@s.whatsapp.net';
    }
}
