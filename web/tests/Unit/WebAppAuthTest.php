<?php

namespace Tests\Unit;

use App\Services\Telegram\WebAppAuth;
use Tests\TestCase;

class WebAppAuthTest extends TestCase
{
    private const BOT_TOKEN = '123456:test-bot-token';

    /** Signs fields the same way Telegram does, so we can test the verifier against known-good input. */
    private function sign(array $fields, string $botToken = self::BOT_TOKEN): string
    {
        $data = $fields;
        ksort($data);
        $checkString = collect($data)->map(fn ($v, $k) => "{$k}={$v}")->implode("\n");
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $data['hash'] = hash_hmac('sha256', $checkString, $secretKey);

        return http_build_query($data);
    }

    public function test_valid_init_data_is_accepted(): void
    {
        $initData = $this->sign([
            'auth_date' => (string) time(),
            'user' => json_encode(['id' => 555111, 'first_name' => 'Sat']),
        ]);

        $result = WebAppAuth::validate($initData, self::BOT_TOKEN);

        $this->assertNotNull($result);
        $this->assertSame(555111, $result['user']['id']);
    }

    public function test_tampered_field_is_rejected(): void
    {
        $initData = $this->sign([
            'auth_date' => (string) time(),
            'user' => json_encode(['id' => 555111]),
        ]);
        $tampered = str_replace('555111', '999999', $initData);

        $this->assertNull(WebAppAuth::validate($tampered, self::BOT_TOKEN));
    }

    public function test_signature_from_a_different_bot_token_is_rejected(): void
    {
        $initData = $this->sign([
            'auth_date' => (string) time(),
            'user' => json_encode(['id' => 555111]),
        ], 'different-bot-token');

        $this->assertNull(WebAppAuth::validate($initData, self::BOT_TOKEN));
    }

    public function test_stale_auth_date_is_rejected(): void
    {
        $initData = $this->sign([
            'auth_date' => (string) now()->subDays(2)->timestamp,
            'user' => json_encode(['id' => 555111]),
        ]);

        $this->assertNull(WebAppAuth::validate($initData, self::BOT_TOKEN));
    }

    public function test_missing_hash_is_rejected(): void
    {
        $this->assertNull(WebAppAuth::validate('auth_date=123&user=%7B%7D', self::BOT_TOKEN));
    }

    public function test_empty_bot_token_is_rejected(): void
    {
        $initData = $this->sign(['auth_date' => (string) time(), 'user' => json_encode(['id' => 1])]);

        $this->assertNull(WebAppAuth::validate($initData, ''));
    }
}
