<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Pembuat barcode SVG murni PHP (F4.4) — tanpa dependensi luar
 * (daftar ekstensi/paket tertutup): EAN-13 dan Code 128 subset B.
 * Keluaran = string SVG batang hitam di atas latar transparan;
 * teks terbaca-manusia dirender pemanggil (Blade) di bawahnya.
 */
class Barcode
{
    /** Pola modul EAN-13 per digit untuk paritas L, G, R. */
    private const EAN_L = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];

    private const EAN_G = ['0100111', '0110011', '0011011', '0100001', '0011101', '0111001', '0000101', '0010001', '0001001', '0010111'];

    private const EAN_R = ['1110010', '1100110', '1101100', '1000010', '1011100', '1001110', '1010000', '1000100', '1001000', '1110100'];

    /** Paritas 6 digit kiri menurut digit pertama. */
    private const EAN_PARITY = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'];

    /** Lebar batang/spasi Code 128 per nilai simbol (0–106); stop = 7 digit. */
    private const C128 = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    /** Digit pemeriksa EAN-13 untuk 12 digit pertama. */
    public static function ean13CheckDigit(string $twelveDigits): int
    {
        if (preg_match('/^\d{12}$/', $twelveDigits) !== 1) {
            throw new InvalidArgumentException('EAN-13 membutuhkan 12 digit awal numerik.');
        }
        $sum = 0;
        foreach (str_split($twelveDigits) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10;
    }

    public static function isValidEan13(string $code): bool
    {
        return preg_match('/^\d{13}$/', $code) === 1
            && self::ean13CheckDigit(substr($code, 0, 12)) === (int) $code[12];
    }

    /** Normalisasi ke 13 digit: 12 digit → tambahkan digit pemeriksa. */
    public static function normalizeEan13(string $code): string
    {
        if (preg_match('/^\d{12}$/', $code) === 1) {
            return $code.self::ean13CheckDigit($code);
        }
        if (self::isValidEan13($code)) {
            return $code;
        }
        throw new InvalidArgumentException('Kode bukan EAN-13 yang sah.');
    }

    public static function ean13Svg(string $code, int $moduleWidth = 2, int $height = 44): string
    {
        $code = self::normalizeEan13($code);
        $parity = self::EAN_PARITY[(int) $code[0]];
        $modules = '00000000000'.'101';
        for ($i = 1; $i <= 6; $i++) {
            $digit = (int) $code[$i];
            $modules .= $parity[$i - 1] === 'L' ? self::EAN_L[$digit] : self::EAN_G[$digit];
        }
        $modules .= '01010';
        for ($i = 7; $i <= 12; $i++) {
            $modules .= self::EAN_R[(int) $code[$i]];
        }
        $modules .= '101'.'0000000';

        return self::modulesToSvg($modules, $moduleWidth, $height);
    }

    /**
     * Urutan nilai simbol Code 128 B: [start, ...data, checksum, stop].
     *
     * @return int[]
     */
    public static function code128Symbols(string $text): array
    {
        if ($text === '') {
            throw new InvalidArgumentException('Code 128 membutuhkan teks non-kosong.');
        }
        $symbols = [104];
        $checksum = 104;
        foreach (str_split($text) as $i => $char) {
            $ord = ord($char);
            if ($ord < 32 || $ord > 126) {
                throw new InvalidArgumentException('Code 128 B hanya mendukung ASCII 32–126.');
            }
            $value = $ord - 32;
            $symbols[] = $value;
            $checksum += ($i + 1) * $value;
        }
        $symbols[] = $checksum % 103;
        $symbols[] = 106;

        return $symbols;
    }

    public static function code128Svg(string $text, int $moduleWidth = 2, int $height = 44): string
    {
        $modules = str_repeat('0', 10);
        foreach (self::code128Symbols($text) as $symbol) {
            $pattern = self::C128[$symbol];
            $isBar = true;
            foreach (str_split($pattern) as $width) {
                $modules .= str_repeat($isBar ? '1' : '0', (int) $width);
                $isBar = ! $isBar;
            }
        }
        $modules .= str_repeat('0', 10);

        return self::modulesToSvg($modules, $moduleWidth, $height);
    }

    /** Tabel pola (untuk uji struktur: setiap entri berjumlah 11 modul, stop 13). */
    public static function code128Patterns(): array
    {
        return self::C128;
    }

    private static function modulesToSvg(string $modules, int $moduleWidth, int $height): string
    {
        $width = strlen($modules) * $moduleWidth;
        $rects = '';
        $inBar = false;
        $start = 0;
        $length = strlen($modules);
        for ($i = 0; $i <= $length; $i++) {
            $isOne = $i < $length && $modules[$i] === '1';
            if ($isOne && ! $inBar) {
                $inBar = true;
                $start = $i;
            } elseif (! $isOne && $inBar) {
                $inBar = false;
                $rects .= '<rect x="'.($start * $moduleWidth).'" y="0" width="'.(($i - $start) * $moduleWidth).'" height="'.$height.'"/>';
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" fill="#000">'.$rects.'</svg>';
    }
}
