<?php

namespace Tests\Unit;

use App\Services\Totp;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TotpTest extends TestCase
{
    private const SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function test_rfc_6238_sha1_vectors_including_64_bit_counter(): void
    {
        $totp = new Totp;
        foreach ([59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471', 1234567890 => '89005924', 2000000000 => '69279037', 20000000000 => '65353130'] as $timestamp => $expected) {
            self::assertSame($expected, $totp->code(self::SECRET, intdiv($timestamp, 30), 8));
            self::assertSame(substr($expected, -6), $totp->code(self::SECRET, intdiv($timestamp, 30)));
        }
    }

    public function test_random_keys_are_distinct_and_exactly_160_bits(): void
    {
        $keys = [];
        for ($i = 0; $i < 50; $i++) {
            $key = (new Totp)->secret();
            self::assertMatchesRegularExpression('/\A[A-Z2-7]{32}\z/', $key);
            $keys[] = $key;
        }
        self::assertCount(50, array_unique($keys));
    }

    public function test_only_bounded_window_and_unused_counters_match(): void
    {
        $totp = new Totp;
        $timestamp = 1234567890;
        $step = intdiv($timestamp, 30);
        foreach ([-1, 0, 1] as $drift) {
            $code = $totp->code(self::SECRET, $step + $drift);
            self::assertSame($step + $drift, $totp->match(self::SECRET, $code, $timestamp, null));
            self::assertNull($totp->match(self::SECRET, $code, $timestamp, $step + $drift));
        }
        self::assertNull($totp->match(self::SECRET, $totp->code(self::SECRET, $step + 2), $timestamp, null));
        foreach (['', '12345', '1234567', ' 123456', '１２３４５６', '1e0000'] as $invalid) {
            self::assertNull($totp->match(self::SECRET, $invalid, $timestamp, null));
        }
    }

    public function test_bad_secret_fails_instead_of_silent_decoding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Totp)->code('not-a-key', 1);
    }
}
