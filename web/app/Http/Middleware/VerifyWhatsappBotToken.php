<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Authenticates the Node.js WhatsApp bot's requests to routes/api.php via a static bearer token. */
class VerifyWhatsappBotToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.whatsapp_bot.api_token');
        $provided = $request->bearerToken();

        if (! $expected || ! $provided || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
