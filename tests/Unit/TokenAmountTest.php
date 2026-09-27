<?php

namespace Tests\Unit;

use App\Services\TokenAmount;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TokenAmountTest extends TestCase
{
    public function test_exact_twenty_token_split(): void
    {
        self::assertSame(['gross' => 20000000, 'fee' => 400000, 'creator' => 19600000], TokenAmount::split(TokenAmount::parse('20')));
    }

    public function test_minimum_and_rounding(): void
    {
        self::assertSame(['gross' => 50, 'fee' => 1, 'creator' => 49], TokenAmount::split(50));
        self::assertSame(['gross' => 99, 'fee' => 1, 'creator' => 98], TokenAmount::split(99));
        self::assertSame(0, TokenAmount::parse('0.000000'));
        self::assertSame('0.000050', TokenAmount::format(50));
        self::assertSame(TokenAmount::MAX_UNITS, TokenAmount::parse('1000000.000000'));
    }

    #[DataProvider('invalidAmounts')]
    public function test_rejects_invalid_amounts(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);
        TokenAmount::parse($amount);
    }

    public static function invalidAmounts(): array
    {
        return array_map(fn ($value) => [$value], ['', '-1', '+1', '1e3', '1,000', '01', ' 1', '1 ', '.1', '1.', '0.000001', '0.000049', '1.1234567', '1000000.000001', '999999999999999999999', 'NaN', 'Infinity']);
    }

    public function test_conservation_round_trip_and_performance(): void
    {
        $start = hrtime(true);
        mt_srand(42);
        for ($i = 0; $i < 10000; $i++) {
            $gross = mt_rand(50, 1000000000);
            $split = TokenAmount::split($gross);
            self::assertSame($gross, $split['fee'] + $split['creator']);
            self::assertSame($gross, TokenAmount::parse(TokenAmount::format($gross)));
        }
        self::assertLessThan(5.0, (hrtime(true) - $start) / 1000000000, '10,000 integer calculations should complete within five seconds.');
    }

    public function test_rejects_zero_purchase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TokenAmount::split(0);
    }

    public function test_rejects_overflow_purchase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TokenAmount::split(PHP_INT_MAX);
    }
}
