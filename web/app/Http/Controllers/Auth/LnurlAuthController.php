<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Lnurl\LnurlAuthService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class LnurlAuthController extends Controller
{
    public function __construct(private readonly LnurlAuthService $lnurl) {}

    /** GET /login — issues a fresh challenge and renders the QR / deep-link page. */
    public function show(): View
    {
        $challenge = $this->lnurl->generateChallenge();
        Session::put('lnurl_auth_session_id', $challenge['session_id']);

        $lnurl = $this->lnurl->buildLnurl($challenge['k1']);

        $qrSvg = Builder::create()
            ->writer(new SvgWriter)
            ->data('lightning:'.$lnurl)
            ->size(320)
            ->margin(10)
            ->build();

        return view('auth.lnurl-login', [
            'lnurl' => $lnurl,
            'qrSvg' => $qrSvg->getString(),
            'sessionId' => $challenge['session_id'],
        ]);
    }

    /**
     * GET /lnurl-auth/callback — the wallet-facing endpoint per LUD-04.
     * Verifies the signature and, on success, upserts the Customer and
     * marks the browser session authenticated for the poller to pick up.
     */
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

        Customer::firstOrCreate(
            ['linking_key' => $validated['key']],
            ['last_login_at' => now()],
        );

        return response()->json(['status' => 'OK']);
    }

    /** GET /lnurl-auth/status/{sessionId} — polled by the login page's JS. */
    public function status(string $sessionId): JsonResponse
    {
        $session = $this->lnurl->getSession($sessionId);

        if (! $session) {
            return response()->json(['status' => 'expired']);
        }

        return response()->json(['status' => $session['status']]);
    }

    /** POST /lnurl-auth/complete — called by the JS once status() reports "authenticated". */
    public function complete(Request $request): RedirectResponse
    {
        $sessionId = Session::get('lnurl_auth_session_id');
        $session = $sessionId ? $this->lnurl->getSession($sessionId) : null;

        if (! $session || $session['status'] !== 'authenticated' || ! $session['linking_key']) {
            return redirect()->route('login')->withErrors(['lnurl' => 'La autenticación no se pudo completar.']);
        }

        $customer = Customer::where('linking_key', $session['linking_key'])->firstOrFail();
        $customer->forceFill(['last_login_at' => now()])->save();

        Auth::guard('customer')->login($customer, remember: true);
        $request->session()->regenerate();

        // Falls back to the dashboard, but honors an intended URL stashed by
        // the auth:customer middleware — e.g. a guest who clicked a job
        // posting on the LED ticker and got bounced here to log in first
        // lands back on /dashboard/escrow instead of the plain dashboard.
        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
