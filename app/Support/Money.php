<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Helper keuangan Lentera (Blueprint A.5) — SATU-SATUNYA jalan aritmetika uang.
 *
 * Representasi internal: integer sen (rupiah × 100). Representasi teks:
 * string desimal ter-normalisasi 2 digit ("1500000.50"). Pembulatan
 * half-up. Float mentah untuk uang DILARANG — tanda tangan int|string
 * + strict_types membuat float melempar TypeError.
 *
 * Semua operasi memakai aritmetika integer murni (tanpa bcmath, tanpa float).
 */
final class Money
{
    private function __construct(private readonly int $cents) {}

    public static function of(int $cents): self
    {
        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Bangun dari string desimal rupiah, mis. "1500000", "1500000.5",
     * "1500000.505" (dibulatkan half-up ke 2 digit). Format id dengan
     * koma desimal ("1500000,50") juga diterima; pemisah ribuan TIDAK.
     */
    public static function fromDecimal(string $amount): self
    {
        $amount = trim($amount);
        if (! preg_match('/^-?\d+([.,]\d+)?$/', $amount)) {
            throw new InvalidArgumentException("Format desimal tidak valid: {$amount}");
        }

        $negative = str_starts_with($amount, '-');
        $amount = ltrim($amount, '-');
        $amount = str_replace(',', '.', $amount);

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $fraction = str_pad($fraction, 3, '0'); // digit ke-3 menentukan pembulatan

        $cents = (int) $whole * 100 + (int) substr($fraction, 0, 2);
        if ((int) $fraction[2] >= 5) {
            $cents++; // half-up
        }

        return new self($negative ? -$cents : $cents);
    }

    public function cents(): int
    {
        return $this->cents;
    }

    /** String desimal ter-normalisasi 2 digit, siap untuk kolom DECIMAL(15,2). */
    public function toDecimalString(): string
    {
        $sign = $this->cents < 0 ? '-' : '';
        $abs = abs($this->cents);

        return sprintf('%s%d.%02d', $sign, intdiv($abs, 100), $abs % 100);
    }

    public function add(Money $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function sub(Money $other): self
    {
        return new self($this->cents - $other->cents);
    }

    /** Kalikan dengan kuantitas bilangan bulat. */
    public function mul(int $qty): self
    {
        return new self($this->cents * $qty);
    }

    /**
     * Persentase dari nilai ini, dibulatkan half-up ke sen terdekat.
     * $percent string desimal, mis. "11" atau "2.5".
     */
    public function percent(string $percent): self
    {
        $percent = trim($percent);
        if (! preg_match('/^\d+([.,]\d+)?$/', $percent)) {
            throw new InvalidArgumentException("Format persen tidak valid: {$percent}");
        }
        $percent = str_replace(',', '.', $percent);
        [$pWhole, $pFraction] = array_pad(explode('.', $percent, 2), 2, '');
        $scale = strlen($pFraction);
        $numerator = (int) ($pWhole.$pFraction); // persen × 10^scale
        $denominator = 100 * (10 ** $scale);

        // hasil = cents × numerator / denominator, half-up, integer murni
        $product = $this->cents * $numerator;
        $sign = $product < 0 ? -1 : 1;
        $abs = abs($product);
        $quotient = intdiv($abs, $denominator);
        $remainder = $abs % $denominator;
        if (2 * $remainder >= $denominator) {
            $quotient++;
        }

        return new self($sign * $quotient);
    }

    /** Perbandingan: -1 / 0 / 1. */
    public function cmp(Money $other): int
    {
        return $this->cents <=> $other->cents;
    }

    public function equals(Money $other): bool
    {
        return $this->cents === $other->cents;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    public function negate(): self
    {
        return new self(-$this->cents);
    }

    public function abs(): self
    {
        return new self(abs($this->cents));
    }

    public function min(Money $other): self
    {
        return $this->cents <= $other->cents ? $this : $other;
    }

    public function max(Money $other): self
    {
        return $this->cents >= $other->cents ? $this : $other;
    }

    /**
     * Format tampilan mengikuti locale aplikasi:
     * id → "Rp1.500.000,50"; en → "Rp1,500,000.50".
     * Memakai pengelompokan manual agar bebas ekstensi & float.
     */
    public function format(): string
    {
        $locale = app()->getLocale();
        $thousands = $locale === 'en' ? ',' : '.';
        $decimal = $locale === 'en' ? '.' : ',';

        $sign = $this->cents < 0 ? '-' : '';
        $abs = abs($this->cents);
        $whole = (string) intdiv($abs, 100);
        $fraction = str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);

        $grouped = '';
        while (strlen($whole) > 3) {
            $grouped = $thousands.substr($whole, -3).$grouped;
            $whole = substr($whole, 0, -3);
        }
        $grouped = $whole.$grouped;

        return $sign.'Rp'.$grouped.$decimal.$fraction;
    }

    public function __toString(): string
    {
        return $this->toDecimalString();
    }
}
