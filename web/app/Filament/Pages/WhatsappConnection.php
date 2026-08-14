<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Live QR / connection-status page for the WhatsApp bot, fed by
 * App\Http\Controllers\Api\WhatsappStatusController pushes. Lets an admin
 * re-pair WhatsApp straight from the browser instead of needing SSH access
 * or Telegram to be working. Polls every 5s (see the Blade view).
 */
class WhatsappConnection extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static ?string $navigationLabel = 'WhatsApp';

    protected static ?string $title = 'Conexión de WhatsApp';

    protected static string $view = 'filament.pages.whatsapp-connection';

    public static function canAccess(): bool
    {
        return Auth::guard('web')->user()?->role === 'admin';
    }

    public function getConnectionState(): array
    {
        return Cache::get('whatsapp:connection', [
            'status' => 'unknown',
            'qr_png_base64' => null,
            'updated_at' => null,
        ]);
    }
}
