<?php

namespace App\Services;

use InvalidArgumentException;

final class TokenAmount
{
    public const DECIMALS = 6;
    public const SCALE = 1000000;
    public const MAX_UNITS = 1000000000000;
    public const FEE_BPS = 200;

    public static function parse(string $amount): int
    {
        if (! preg_match('/\A(0|[1-9][0-9]{0,6})(?:\.([0-9]{1,6}))?\z/D', $amount, $match)) {
            throw new InvalidArgumentException('Enter an unsigned decimal with at most six decimal places.');
        }
        $units = ((int) $match[1] * self::SCALE) + (int) str_pad($match[2] ?? '', self::DECIMALS, '0');
        if ($units > self::MAX_UNITS || ($units > 0 && $units < 50)) {
            throw new InvalidArgumentException('Use zero for free content, or 0.000050 to 1000000 TEST.');
        }
        return $units;
    }

    /** @return array{gross: int, fee: int, creator: int} */
    public static function split(int $gross): array
    {
        if ($gross < 50 || $gross > self::MAX_UNITS) {
            throw new InvalidArgumentException('Purchase amount outside the supported range.');
        }
        // Floor the fee to the smallest token unit; the creator receives the remainder.
        $fee = intdiv($gross * self::FEE_BPS, 10000);
        return ['gross' => $gross, 'fee' => $fee, 'creator' => $gross - $fee];
    }

    public static function format(int $units): string
    {
        if ($units < 0 || $units > self::MAX_UNITS) {
            throw new InvalidArgumentException('Amount outside the supported range.');
        }
        return intdiv($units, self::SCALE).'.'.str_pad((string) ($units % self::SCALE), self::DECIMALS, '0', STR_PAD_LEFT);
    }
}
