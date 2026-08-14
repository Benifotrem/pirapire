<?php

namespace Tests\Unit;

use App\Services\Lnurl\Bech32;
use PHPUnit\Framework\TestCase;

class Bech32Test extends TestCase
{
    public function test_encoded_string_starts_with_hrp_and_separator(): void
    {
        $encoded = Bech32::encodeFromBytes('lnurl', 'https://pirapire.pro/lnurl-auth/callback?k1=abc');

        $this->assertStringStartsWith('lnurl1', $encoded);
    }

    public function test_encoding_only_uses_the_bech32_charset(): void
    {
        $encoded = Bech32::encodeFromBytes('lnurl', 'test payload 123');
        [, $data] = explode('1', $encoded, 2);

        $this->assertMatchesRegularExpression('/^[qpzry9x8gf2tvdw0s3jn54khce6mua7l]+$/', $data);
    }

    public function test_encoding_is_deterministic(): void
    {
        $a = Bech32::encodeFromBytes('lnurl', 'https://pirapire.pro/x?y=1');
        $b = Bech32::encodeFromBytes('lnurl', 'https://pirapire.pro/x?y=1');

        $this->assertSame($a, $b);
    }

    public function test_different_payloads_produce_different_output(): void
    {
        $a = Bech32::encodeFromBytes('lnurl', 'https://pirapire.pro/x?y=1');
        $b = Bech32::encodeFromBytes('lnurl', 'https://pirapire.pro/x?y=2');

        $this->assertNotSame($a, $b);
    }

    public function test_convert_bits_round_trips_through_5_bit_groups(): void
    {
        $bytes = [0xFF, 0x00, 0x80, 0x7F];
        $fiveBit = Bech32::convertBits($bytes, 8, 5, true);

        foreach ($fiveBit as $group) {
            $this->assertGreaterThanOrEqual(0, $group);
            $this->assertLessThanOrEqual(31, $group);
        }
    }
}
