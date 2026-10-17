<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use Tests\TestCase;
use TypeError;

/**
 * Money (Blueprint A.5): aritmetika integer sen, half-up, tanpa float/bcmath.
 * Memakai Tests\TestCase (bukan PHPUnit murni) karena format() membaca locale aplikasi.
 */
class MoneyTest extends TestCase
{
    public function test_from_decimal_parses_and_normalizes(): void
    {
        $this->assertSame(150000050, Money::fromDecimal('1500000.5')->cents());
        $this->assertSame('1500000.50', Money::fromDecimal('1500000.5')->toDecimalString());
        $this->assertSame('0.00', Money::zero()->toDecimalString());
        $this->assertSame(-150, Money::fromDecimal('-1.50')->cents());
    }

    public function test_from_decimal_accepts_indonesian_comma(): void
    {
        $this->assertSame('2500.75', Money::fromDecimal('2500,75')->toDecimalString());
    }

    public function test_from_decimal_rounds_half_up(): void
    {
        $this->assertSame('2.01', Money::fromDecimal('2.005')->toDecimalString());
        $this->assertSame('2.00', Money::fromDecimal('2.004')->toDecimalString());
        $this->assertSame('0.01', Money::fromDecimal('0.005')->toDecimalString());
        $this->assertSame('-1.01', Money::fromDecimal('-1.005')->toDecimalString());
    }

    public function test_from_decimal_rejects_garbage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromDecimal('1.500.000,50'); // pemisah ribuan tidak diterima
    }

    public function test_float_input_is_a_type_error_under_strict_types(): void
    {
        // Berkas ini memakai declare(strict_types=1): tanda tangan int|string
        // pada Money menolak float dengan TypeError, bukan koersi diam-diam.
        $this->expectException(TypeError::class);
        Money::fromDecimal(1.5); // @phpstan-ignore-line — disengaja untuk uji
    }

    public function test_add_sub_mul_are_exact(): void
    {
        $a = Money::fromDecimal('100000.10');
        $b = Money::fromDecimal('0.20');

        // Dengan float: 100000.10 + 0.20 bisa berujung ...29999; di sini eksak.
        $this->assertSame('100000.30', $a->add($b)->toDecimalString());
        $this->assertSame('99999.90', $a->sub($b)->toDecimalString());
        $this->assertSame('300000.30', $a->mul(3)->toDecimalString());
        $this->assertSame('0.00', $a->sub($a)->toDecimalString());
    }

    public function test_percent_rounds_half_up(): void
    {
        // 11% dari 100.05 = 11.0055 → 11.01
        $this->assertSame('11.01', Money::fromDecimal('100.05')->percent('11')->toDecimalString());
        // 2.5% dari 1000.00 = 25.00 eksak
        $this->assertSame('25.00', Money::fromDecimal('1000')->percent('2.5')->toDecimalString());
        // 10% dari 0.05 = 0.005 → 0.01 (half-up)
        $this->assertSame('0.01', Money::fromDecimal('0.05')->percent('10')->toDecimalString());
    }

    public function test_comparisons(): void
    {
        $a = Money::fromDecimal('10.00');
        $b = Money::fromDecimal('10.01');

        $this->assertSame(-1, $a->cmp($b));
        $this->assertSame(0, $a->cmp(Money::fromDecimal('10')));
        $this->assertSame(1, $b->cmp($a));
        $this->assertTrue($a->equals(Money::of(1000)));
        $this->assertTrue(Money::zero()->isZero());
        $this->assertTrue(Money::fromDecimal('-0.01')->isNegative());
        $this->assertSame($a, $a->min($b));
        $this->assertSame($b, $a->max($b));
        $this->assertSame('10.00', $a->negate()->abs()->toDecimalString());
    }

    public function test_format_follows_locale(): void
    {
        $money = Money::fromDecimal('1500000.50');

        app()->setLocale('id');
        $this->assertSame('Rp1.500.000,50', $money->format());

        app()->setLocale('en');
        $this->assertSame('Rp1,500,000.50', $money->format());
    }
}
