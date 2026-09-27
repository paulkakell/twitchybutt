<?php

namespace App\Services;

use InvalidArgumentException;

/** RFC 6238, SHA-1, 30-second steps. No network service receives the shared key. */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function secret(): string
    {
        $buffer = 0;
        $bits = 0;
        $encoded = '';
        foreach (unpack('C*', random_bytes(20)) as $byte) {
            $buffer = ($buffer << 8) | $byte;
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $encoded .= self::ALPHABET[($buffer >> $bits) & 31];
            }
            $buffer &= (1 << $bits) - 1;
        }

        return $encoded;
    }

    public function code(string $secret, int $counter, int $digits = 6): string
    {
        if (preg_match('/\A[A-Z2-7]{32}\z/', $secret) !== 1 || $counter < 0 || ! in_array($digits, [6, 8], true)) {
            throw new InvalidArgumentException('Invalid one-time-code parameters.');
        }
        $buffer = 0;
        $bits = 0;
        $raw = '';
        foreach (str_split($secret) as $character) {
            $buffer = ($buffer << 5) | strpos(self::ALPHABET, $character);
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $raw .= chr(($buffer >> $bits) & 255);
            }
            $buffer &= (1 << $bits) - 1;
        }
        $mac = hash_hmac('sha1', pack('N2', intdiv($counter, 4294967296), $counter & 0xFFFFFFFF), $raw, true);
        $offset = ord($mac[19]) & 15;
        $value = unpack('N', substr($mac, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    public function match(string $secret, string $code, int $timestamp, ?int $lastCounter): ?int
    {
        if (preg_match('/\A[0-9]{6}\z/', $code) !== 1 || $timestamp < 0) {
            return null;
        }
        $step = intdiv($timestamp, 30);
        $match = null;
        foreach ([-1, 0, 1] as $drift) {
            $candidate = $step + $drift;
            if ($candidate >= 0 && hash_equals($this->code($secret, $candidate), $code) && ($lastCounter === null || $candidate > $lastCounter)) {
                $match = $candidate;
            }
        }

        return $match;
    }
}
