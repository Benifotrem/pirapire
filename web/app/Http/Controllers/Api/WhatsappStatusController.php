<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Receives connection-state pushes from the Node.js WhatsApp bot (Baileys
 * `connection.update` — see whatsapp-bot/src/baileys/connection.ts) so the
 * Filament admin panel can show a live QR/connected/disconnected page
 * (App\Filament\Pages\WhatsappConnection) without depending on Telegram or
 * SSH access to the VPS. Bot-authenticated like the rest of routes/api.php.
 */
class WhatsappStatusController extends Controller
{
    private const CACHE_KEY = 'whatsapp:connection';

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:qr,connected,disconnected',
            'qr_png_base64' => 'nullable|string',
        ]);

        Cache::forever(self::CACHE_KEY, [
            'status' => $validated['status'],
            'qr_png_base64' => $validated['qr_png_base64'] ?? null,
            'updated_at' => now()->toIso8601String(),
        ]);

        return response()->json(['status' => 'ok']);
    }
}
