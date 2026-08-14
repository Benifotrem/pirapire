<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Lnurl\LnurlAuthService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

/**
 * Lightning-wallet login for the Filament admin panel (App\Models\User),
 * built on the same LNURL-auth (LUD-04) machinery as the customer login
 * (App\Http\Controllers\Auth\LnurlAuthController) but kept as a separate
 * controller/routes because the two must never share behavior:
 *
 *  - Customer login auto-creates an account for any wallet that signs the
 *    challenge (that's the point — sovereign, permissionless signup).
 *  - Staff login must NEVER auto-create a User. A wallet only works here
 *    once its linking key has been explicitly attached to an existing
 *    admin/support account.
 *
 * That attachment ("linking") reuses this exact same page: if the browser
 * already carries an authenticated `web` guard session (i.e. an admin who
 * just signed in with their password) when they open /staff-login and scan
 * a QR, complete() treats it as "attach this wallet to my account" instead
 * of "log me in".
 */
class StaffLnurlAuthController extends Controller
{
    private const SESSION_KEY = 'staff_lnurl_auth_session_id';

    public function __construct(private readonly LnurlAuthService $lnurl) {}

    public function show(): View
    {
        $challenge = $this->lnurl->generateChallenge();
        Session::put(self::SESSION_KEY, $challenge['session_id']);

        $lnurlString = $this->lnurl->buildLnurl($challenge['k1'], 'staff-lnurl-auth.callback');

        $qrSvg = Builder::create()
            ->writer(new SvgWriter)
            ->data('lightning:'.$lnurlString)
            ->size(320)
            ->margin(10)
            ->build();

        return view('auth.staff-lnurl-login', [
            'lnurl' => $lnurlString,
            'qrSvg' => $qrSvg->getString(),
            'sessionId' => $challenge['session_id'],
            'linking' => Auth::guard('web')->check(),
        ]);
    }

    /** GET /staff-lnurl-auth/callback — the wallet-facing endpoint per LUD-04. */
    public function callback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tag' => 'required|in:login',
            'k1' => 'required|string|size:64',
            'sig' => 'required|string',
            'key' => 'required|string',
        ]);

        $verified = $this->lnurl->verifySignature(
            $validated['k1'],
            $validated['sig'],
            $validated['key'],
        );

        if (! $verified) {
            return response()->json(['status' => 'ERROR', 'reason' => 'Invalid signature'], 400);
        }

        $marked = $this->lnurl->markAuthenticated($validated['k1'], $validated['key']);
        if (! $marked) {
            return response()->json(['status' => 'ERROR', 'reason' => 'Unknown or expired challenge'], 400);
        }

        return response()->json(['status' => 'OK']);
    }

    public function status(string $sessionId): JsonResponse
    {
        $session = $this->lnurl->getSession($sessionId);

        if (! $session) {
            return response()->json(['status' => 'expired']);
        }

        return response()->json(['status' => $session['status']]);
    }

    public function complete(Request $request): RedirectResponse
    {
        $sessionId = Session::get(self::SESSION_KEY);
        $session = $sessionId ? $this->lnurl->getSession($sessionId) : null;

        if (! $session || $session['status'] !== 'authenticated' || ! $session['linking_key']) {
            return redirect()->route('staff-login')->withErrors(['lnurl' => 'La autenticación no se pudo completar.']);
        }

        $linkingKey = $session['linking_key'];

        if (Auth::guard('web')->check()) {
            return $this->attachWalletToCurrentUser($linkingKey);
        }

        $user = User::where('linking_key', $linkingKey)
            ->whereIn('role', ['admin', 'support'])
            ->first();

        if (! $user) {
            return redirect()->route('staff-login')->withErrors([
                'lnurl' => 'Esta billetera no está vinculada a ninguna cuenta de administrador. '
                    .'Iniciá sesión con tu contraseña y vinculala desde el menú de usuario.',
            ]);
        }

        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();

        return redirect('/admin');
    }

    private function attachWalletToCurrentUser(string $linkingKey): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        $ownedByAnotherAccount = User::where('linking_key', $linkingKey)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($ownedByAnotherAccount) {
            return redirect()->route('staff-login')->withErrors([
                'lnurl' => 'Esa billetera ya está vinculada a otra cuenta de administrador.',
            ]);
        }

        $user->forceFill(['linking_key' => $linkingKey])->save();

        return redirect('/admin')->with('status', 'Billetera Lightning vinculada. Ya podés iniciar sesión con ella.');
    }
}
