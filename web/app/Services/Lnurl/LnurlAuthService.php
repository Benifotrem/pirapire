<?php

namespace App\Services\Lnurl;

use Elliptic\EC;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Implements LNURL-auth (LUD-04): passwordless login where a wallet proves
 * control of a secp256k1 keypair by signing a server-issued challenge (k1).
 *
 * Flow:
 *  1. generateChallenge() mints a k1 and caches a "pending" session under it.
 *  2. buildLnurl() bech32-encodes the callback URL for the QR code / deep link.
 *  3. The wallet GETs the callback with ?k1=&sig=&key=; verifySignature()
 *     checks the ECDSA signature, then markAuthenticated() flips the session
 *     to "authenticated" against the wallet's linking public key.
 *  4. The browser polls checkStatus() (session_id from step 1) until it sees
 *     "authenticated" and logs the user in / creates the account.
 */
class LnurlAuthService
{
    private const CACHE_PREFIX = 'lnurl-auth:';

    private const CHALLENGE_TTL_SECONDS = 300; // 5 minutes, per LUD-04 guidance

    public function generateChallenge(): array
    {
        $sessionId = Str::random(32);
        $k1 = bin2hex(random_bytes(32));

        Cache::put(self::CACHE_PREFIX.$sessionId, [
            'k1' => $k1,
            'status' => 'pending',
            'linking_key' => null,
        ], self::CHALLENGE_TTL_SECONDS);

        $this->indexK1($sessionId, $k1);

        return ['session_id' => $sessionId, 'k1' => $k1];
    }

    public function buildLnurl(string $k1, string $callbackRouteName = 'lnurl-auth.callback'): string
    {
        $callbackUrl = route($callbackRouteName, ['tag' => 'login', 'k1' => $k1]);

        return strtoupper(Bech32::encodeFromBytes('lnurl', $callbackUrl));
    }

    public function getSession(string $sessionId): ?array
    {
        return Cache::get(self::CACHE_PREFIX.$sessionId);
    }

    private function findSessionIdByK1(string $k1): ?string
    {
        // k1 is generated per-session and only ever cached under its owning
        // session id, so we index it separately to allow lookup by k1 alone
        // (the wallet callback only carries k1, not the session id).
        return Cache::get(self::CACHE_PREFIX.'k1-index:'.$k1);
    }

    public function indexK1(string $sessionId, string $k1): void
    {
        Cache::put(self::CACHE_PREFIX.'k1-index:'.$k1, $sessionId, self::CHALLENGE_TTL_SECONDS);
    }

    /**
     * Verifies the wallet's ECDSA signature over k1 using its linking public
     * key, per LUD-04. $sigHex is a DER-encoded signature, $keyHex is the
     * compressed secp256k1 public key, both hex-encoded.
     */
    public function verifySignature(string $k1Hex, string $sigHex, string $keyHex): bool
    {
        if (! ctype_xdigit($k1Hex) || ! ctype_xdigit($sigHex) || ! ctype_xdigit($keyHex)) {
            return false;
        }

        try {
            $ec = new EC('secp256k1');
            $publicKey = $ec->keyFromPublic($keyHex, 'hex');

            // LNURL-auth signs the raw k1 bytes directly (k1 is itself a
            // server-generated random 32-byte value), not a re-hash of it.
            return (bool) $publicKey->verify($k1Hex, $sigHex);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Called from the wallet-facing callback route after signature
     * verification succeeds. Marks the session authenticated so the
     * browser's poller can complete the login.
     */
    public function markAuthenticated(string $k1, string $linkingKey): bool
    {
        $sessionId = $this->findSessionIdByK1($k1);
        if (! $sessionId) {
            return false;
        }

        $session = $this->getSession($sessionId);
        if (! $session || $session['k1'] !== $k1) {
            return false;
        }

        Cache::put(self::CACHE_PREFIX.$sessionId, [
            'k1' => $k1,
            'status' => 'authenticated',
            'linking_key' => $linkingKey,
        ], self::CHALLENGE_TTL_SECONDS);

        return true;
    }
}
