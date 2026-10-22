<?php

namespace App\Modules\Sales\Services;

/**
 * Validator precal MANUAL per item Lensa RX (ADR-019, paritas
 * RxParameterValidator Artesis). Precal terikat FRAME, bukan turunan
 * resep: 11 kunci kanonik diinput kasir, BOLEH KOSONG saat penjualan
 * (dilengkapi kemudian dari detail penjualan); kelengkapan dicatat
 * sebagai flag precal_complete. PDR/PDL BUKAN kunci precal — keduanya
 * hanya hidup di resep (aturan mutlak).
 */
class RxPrecalValidator
{
    /** @var list<string> */
    public const KEYS = ['pvr', 'pvl', 'a', 'b', 'dbl', 'ed', 'vd', 'pa', 'wrap', 'de', 'type_frame'];

    /** @var list<string> */
    private const NUMERIC_KEYS = ['pvr', 'pvl', 'a', 'b', 'dbl', 'ed', 'vd', 'pa', 'wrap'];

    /**
     * Validasi input mentah precal.
     *
     * @param  array<string, mixed>|null  $input
     * @return array<string, string> peta kunci precal => pesan kesalahan
     */
    public function validate(?array $input): array
    {
        if ($input === null) {
            return [];
        }

        $errors = [];
        foreach (self::KEYS as $key) {
            $value = $input[$key] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '')) {
                continue;
            }
            if (in_array($key, self::NUMERIC_KEYS, true)) {
                if (! is_numeric(is_string($value) ? trim($value) : $value)) {
                    $errors[$key] = __('ui.sales.precal_numeric', ['field' => strtoupper($key)]);
                }
            } elseif ($key === 'de') {
                if (! in_array(strtoupper(trim((string) $value)), ['R', 'L'], true)) {
                    $errors[$key] = __('ui.sales.precal_de_invalid');
                }
            } elseif ($key === 'type_frame') {
                if (mb_strlen(trim((string) $value)) > 100) {
                    $errors[$key] = __('ui.sales.precal_type_frame_max');
                }
            }
        }

        return $errors;
    }

    /**
     * Normalisasi input menjadi peta 11 kunci (null bila kosong) +
     * flag kelengkapan. Input diasumsikan lolos validate().
     *
     * @param  array<string, mixed>|null  $input
     * @return array{precal: array<string, string|null>, complete: bool}
     */
    public function normalize(?array $input): array
    {
        $precal = [];
        $complete = true;
        foreach (self::KEYS as $key) {
            $value = $input[$key] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            if ($value === null || $value === '') {
                $precal[$key] = null;
                $complete = false;
                continue;
            }
            $precal[$key] = $key === 'de' ? strtoupper((string) $value) : (string) $value;
        }

        return ['precal' => $precal, 'complete' => $complete];
    }

    /** Apakah peta precal tersimpan sudah lengkap 11 kunci. */
    public function isComplete(?array $precal): bool
    {
        if ($precal === null) {
            return false;
        }
        foreach (self::KEYS as $key) {
            if (($precal[$key] ?? null) === null || $precal[$key] === '') {
                return false;
            }
        }

        return true;
    }
}
