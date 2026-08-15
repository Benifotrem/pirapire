<?php

namespace App\Services\Telegram;

/**
 * Validates the `initData` string a Telegram Mini App's JS client
 * (`Telegram.WebApp.initData`) sends on every request, per Telegram's
 * documented Mini Apps auth scheme:
 *
 *   secret_key = HMAC_SHA256(<bot_token>, "WebAppData")
 *   hash       = HMAC_SHA256(<data_check_string>, secret_key)
 *
 * where data_check_string is every field except `hash`, sorted by key and
 * joined as "key=value" lines. Each Mini App is checked against its own
 * bot's token — the customer bot and the admin bot are different bots, so
 * neither can forge a session for the other's Mini App.
 */
class WebAppAuth
{
    private const MAX_AGE_SECONDS = 86400;

    /** @return array<string, mixed>|null Parsed fields (with `user` json-decoded), or null if invalid/stale. */
    public static function validate(string $initData, string $botToken): ?array
    {
        parse_str($initData, $data);

        if (! isset($data['hash']) || $botToken === '') {
            return null;
        }

        $hash = $data['hash'];
        unset($data['hash']);

        ksort($data);
        $checkString = collect($data)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $computedHash = hash_hmac('sha256', $checkString, $secretKey);

        if (! hash_equals($computedHash, $hash)) {
            return null;
        }

        $authDate = (int) ($data['auth_date'] ?? 0);
        if ($authDate <= 0 || (time() - $authDate) > self::MAX_AGE_SECONDS) {
            return null;
        }

        if (isset($data['user'])) {
            $data['user'] = json_decode((string) $data['user'], true);
        }

        return $data;
    }
}
