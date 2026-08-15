<?php

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\EscrowDispute;
use App\Models\EscrowJob;
use App\Models\User;
use App\Services\Escrow\EscrowService;
use App\Services\Lightning\LnbitsClient;
use App\Services\Stats\PlatformStatsService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * JSON API behind the admin Mini App (resources/views/miniapp/admin.blade.php)
 * — a mobile-friendly view of the operationally urgent parts of the
 * Filament panel (dashboard metrics, wallet balance, escrow jobs, and
 * resolving disputes). Full CRUD on customers/alerts/VIPs/staff stays
 * Filament-only; this covers what you'd actually want to check or act on
 * from a phone. Auth is done by AuthenticateAdminMiniApp, which resolves
 * the admin User from Telegram's initData and attaches it as the `admin`
 * request attribute — only Users already linked via "/vincular CODE" can
 * reach any of this.
 */
class AdminController extends Controller
{
    public function __construct(private readonly EscrowService $escrow) {}

    private function admin(Request $request): User
    {
        return $request->attributes->get('admin');
    }

    public function me(Request $request): JsonResponse
    {
        $admin = $this->admin($request);

        return response()->json([
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => $admin->role,
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json(app(PlatformStatsService::class)->compute());
    }

    public function wallet(Request $request): JsonResponse
    {
        if ($this->admin($request)->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $wallet = Cache::remember('admin-dashboard:lnbits-wallet', 30, function () {
            try {
                return app(LnbitsClient::class)->getWalletDetails();
            } catch (Throwable) {
                return null;
            }
        });

        if ($wallet === null) {
            return response()->json(['message' => 'No se pudo contactar a LNbits'], 503);
        }

        return response()->json([
            'balance_sats' => (int) round(($wallet['balance'] ?? 0) / 1000),
            'name' => $wallet['name'] ?? 'Pirapire',
        ]);
    }

    public function escrowJobs(Request $request): JsonResponse
    {
        $query = EscrowJob::query()->with('creator')->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->limit(50)->get());
    }

    public function showEscrowJob(EscrowJob $job): JsonResponse
    {
        return response()->json($job->load('creator', 'disputes'));
    }

    public function disputes(Request $request): JsonResponse
    {
        $query = EscrowDispute::query()->with('escrowJob', 'openedBy')->latest();

        if (! $request->boolean('all')) {
            $query->where('status', 'open');
        }

        return response()->json($query->limit(50)->get());
    }

    public function showDispute(EscrowDispute $dispute): JsonResponse
    {
        return response()->json($dispute->load('escrowJob', 'openedBy'));
    }

    public function resolveDispute(Request $request, EscrowDispute $dispute): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:release,refund',
            'payout_bolt11' => 'required|string',
            'resolution_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $job = $dispute->escrowJob;

            if ($validated['action'] === 'release') {
                $this->escrow->release($job, $validated['payout_bolt11']);
            } else {
                $this->escrow->refund($job, $validated['payout_bolt11']);
            }

            $dispute->update([
                'resolution_notes' => $validated['resolution_notes'] ?? null,
                'resolved_by_user_id' => $this->admin($request)->id,
            ]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException) {
            return response()->json(['message' => 'No se pudo contactar al proveedor de pagos. Probá de nuevo en unos minutos.'], 503);
        }

        return response()->json($dispute->fresh(['escrowJob', 'openedBy']));
    }
}
