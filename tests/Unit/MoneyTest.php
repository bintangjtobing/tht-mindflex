<?php

declare(strict_types=1);

namespace Mindflex\Tests\Unit;

use InvalidArgumentException;
use Mindflex\Support\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_it_parses_decimal_strings_into_cents(): void
    {
        self::assertSame(4500, Money::fromDecimal('45')->cents());
        self::assertSame(3550, Money::fromDecimal('35.50')->cents());
        self::assertSame(2595, Money::fromDecimal('25.95')->cents());
        self::assertSame(7457, Money::fromDecimal('74.57')->cents());
    }

    public function test_it_rounds_the_third_decimal_place(): void
    {
        self::assertSame(1235, Money::fromDecimal('12.345')->cents());
        self::assertSame(1234, Money::fromDecimal('12.344')->cents());
    }

    public function test_it_rejects_values_that_are_not_numbers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal('45; DROP TABLE tutors');
    }

    /**
     * Menjumlahkan 0.1 sepuluh kali dengan float menghasilkan 0.9999999999999999.
     * Integer cent menjaga totalnya tetap bulat.
     */
    public function test_repeated_addition_stays_exact(): void
    {
        $total = Money::zero();

        for ($index = 0; $index < 10; $index++) {
            $total = $total->add(Money::fromDecimal('0.10'));
        }

        self::assertSame(100, $total->cents());
        self::assertSame('$1.00', $total->format());
    }

    public function test_it_multiplies_by_weekly_hours(): void
    {
        $weeklyCost = Money::fromDecimal('35.50')->multiplyBy(3);

        self::assertSame(10650, $weeklyCost->cents());
        self::assertSame('$106.50', $weeklyCost->format());
    }

    public function test_it_compares_amounts(): void
    {
        $cost = Money::fromDecimal('100.00');
        $budget = Money::fromDecimal('60.00');

        self::assertTrue($cost->isGreaterThan($budget));
        self::assertSame(4000, $cost->subtract($budget)->cents());
    }

    public function test_it_formats_using_the_configured_currency(): void
    {
        self::assertSame('$1,234.56', Money::fromCents(123456)->format('USD'));
        self::assertSame('Rp1,234.56', Money::fromCents(123456)->format('IDR'));
        self::assertSame('-$5.00', Money::fromCents(-500)->format('USD'));
    }
}
