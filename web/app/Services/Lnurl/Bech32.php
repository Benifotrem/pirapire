<?php

namespace App\Services\Lnurl;

use InvalidArgumentException;

/**
 * Minimal BIP-0173 bech32 encoder used to turn an LNURL-auth callback URL
 * into the "LNURL1..." string that wallets scan as a QR code (LUD-01).
 * Only encoding is implemented — the app is always the one generating
 * lnurl strings, never decoding wallet-supplied ones.
 */
class Bech32
{
    private const CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

    public static function encode(string $hrp, array $data): string
    {
        $combined = array_merge($data, self::createChecksum($hrp, $data));

        $result = $hrp.'1';
        foreach ($combined as $value) {
            $result .= self::CHARSET[$value];
        }

        return $result;
    }

    /** Encodes an arbitrary byte string as bech32 with the given human-readable part. */
    public static function encodeFromBytes(string $hrp, string $bytes): string
    {
        return self::encode($hrp, self::convertBits(array_values(unpack('C*', $bytes) ?: []), 8, 5, true));
    }

    private static function polymod(array $values): int
    {
        $generator = [0x3B6A57B2, 0x26508E6D, 0x1EA119FA, 0x3D4233DD, 0x2A1462B3];
        $chk = 1;

        foreach ($values as $value) {
            $top = $chk >> 25;
            $chk = (($chk & 0x1FFFFFF) << 5) ^ $value;
            for ($i = 0; $i < 5; $i++) {
                $chk ^= (($top >> $i) & 1) ? $generator[$i] : 0;
            }
        }

        return $chk;
    }

    private static function hrpExpand(string $hrp): array
    {
        $result = [];
        $len = strlen($hrp);

        for ($i = 0; $i < $len; $i++) {
            $result[] = ord($hrp[$i]) >> 5;
        }
        $result[] = 0;
        for ($i = 0; $i < $len; $i++) {
            $result[] = ord($hrp[$i]) & 31;
        }

        return $result;
    }

    private static function createChecksum(string $hrp, array $data): array
    {
        $values = array_merge(self::hrpExpand($hrp), $data, [0, 0, 0, 0, 0, 0]);
        $mod = self::polymod($values) ^ 1;

        $checksum = [];
        for ($i = 0; $i < 6; $i++) {
            $checksum[] = ($mod >> (5 * (5 - $i))) & 31;
        }

        return $checksum;
    }

    /** Converts a byte array between arbitrary bit-widths (used for 8-bit -> 5-bit groups). */
    public static function convertBits(array $data, int $fromBits, int $toBits, bool $pad): array
    {
        $acc = 0;
        $bits = 0;
        $result = [];
        $maxv = (1 << $toBits) - 1;

        foreach ($data as $value) {
            if ($value < 0 || ($value >> $fromBits) !== 0) {
                throw new InvalidArgumentException('Invalid value for bit conversion.');
            }

            $acc = (($acc << $fromBits) | $value) & 0xFFFFFFFF;
            $bits += $fromBits;

            while ($bits >= $toBits) {
                $bits -= $toBits;
                $result[] = ($acc >> $bits) & $maxv;
            }
        }

        if ($pad && $bits > 0) {
            $result[] = ($acc << ($toBits - $bits)) & $maxv;
        } elseif (! $pad && ($bits >= $fromBits || (($acc << ($toBits - $bits)) & $maxv) !== 0)) {
            throw new InvalidArgumentException('Invalid padding in bit conversion.');
        }

        return $result;
    }
}
