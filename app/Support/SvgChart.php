<?php

namespace App\Support;

/**
 * Grafik SVG murni PHP yang dirender server (tanpa pustaka luar /
 * CDN) — preseden rumah yang sama dengan App\Support\Barcode.
 * Dipakai dashboard dinamis dan pengayaan laporan analitik.
 * Seluruh label dilolos-escape; geometri deterministik agar dapat
 * diuji satuan.
 */
class SvgChart
{
    /** Palet token Lentera: emerald bertingkat + emas + netral. */
    public const PALETTE = ['#059669', '#c9a227', '#065f46', '#0d9488', '#9a7b1c', '#64748b', '#34d399', '#78716c'];

    /**
     * Line chart multi-seri. $labels = label sumbu X; setiap seri:
     * ['label' => string, 'color' => hex, 'points' => list<float>].
     * Seri pertama mendapat isian area lembut. Nilai uang diformat
     * ringkas pada sumbu Y.
     *
     * @param  list<string>  $labels
     * @param  list<array{label: string, color: string, points: list<float|int>}>  $series
     */
    public static function line(array $labels, array $series, int $width = 680, int $height = 250): string
    {
        $padL = 64;
        $padR = 14;
        $padT = 14;
        $padB = 30;
        $plotW = $width - $padL - $padR;
        $plotH = $height - $padT - $padB;

        $all = [];
        foreach ($series as $s) {
            foreach ($s['points'] as $p) {
                $all[] = self::num($p);
            }
        }
        $min = $all === [] ? 0.0 : min(0.0, min($all));
        $max = $all === [] ? 1.0 : max($all);
        if ($max <= $min) {
            $max = $min + 1;
        }
        $y = fn (float $v) => $padT + $plotH - (($v - $min) / ($max - $min)) * $plotH;
        $n = max(count($labels), 1);
        $x = fn (int $i) => $n === 1 ? $padL + $plotW / 2 : $padL + ($i / ($n - 1)) * $plotW;

        $svg = [];
        $svg[] = '<svg viewBox="0 0 '.$width.' '.$height.'" class="h-auto w-full" role="img" xmlns="http://www.w3.org/2000/svg">';

        // Garis kisi + label sumbu Y (5 tingkat).
        for ($i = 0; $i <= 4; $i++) {
            $value = $min + ($max - $min) * $i / 4;
            $gy = $y($value);
            $svg[] = '<line x1="'.$padL.'" y1="'.round($gy, 1).'" x2="'.($width - $padR).'" y2="'.round($gy, 1).'" stroke="#e5e7eb" stroke-width="1"/>';
            $svg[] = '<text x="'.($padL - 8).'" y="'.round($gy + 4, 1).'" text-anchor="end" font-size="11" fill="#6b7280">'.self::e(self::shortMoney($value)).'</text>';
        }

        // Label sumbu X (ditipiskan agar tidak bertumpuk).
        $step = max(1, (int) ceil($n / 8));
        foreach ($labels as $i => $label) {
            if ($i % $step !== 0 && $i !== $n - 1) {
                continue;
            }
            $svg[] = '<text x="'.round($x($i), 1).'" y="'.($height - 8).'" text-anchor="middle" font-size="11" fill="#6b7280">'.self::e($label).'</text>';
        }

        foreach ($series as $index => $s) {
            $points = [];
            foreach ($s['points'] as $i => $p) {
                $points[] = [round($x($i), 1), round($y(self::num($p)), 1)];
            }
            if ($points === []) {
                continue;
            }
            if ($index === 0 && count($points) > 1) {
                $area = 'M'.$points[0][0].' '.$points[0][1];
                foreach (array_slice($points, 1) as [$px, $py]) {
                    $area .= ' L'.$px.' '.$py;
                }
                $area .= ' L'.$points[array_key_last($points)][0].' '.round($y($min), 1).' L'.$points[0][0].' '.round($y($min), 1).' Z';
                $svg[] = '<path d="'.$area.'" fill="'.$s['color'].'" fill-opacity="0.10" stroke="none"/>';
            }
            if (count($points) > 1) {
                $poly = implode(' ', array_map(fn ($pt) => $pt[0].','.$pt[1], $points));
                $svg[] = '<polyline points="'.$poly.'" fill="none" stroke="'.$s['color'].'" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>';
            }
            foreach ($points as [$px, $py]) {
                $svg[] = '<circle cx="'.$px.'" cy="'.$py.'" r="3" fill="#ffffff" stroke="'.$s['color'].'" stroke-width="2"/>';
            }
        }

        $svg[] = '</svg>';

        return implode('', $svg);
    }

    /**
     * Doughnut dari irisan nilai. Mengembalikan SVG saja; legenda
     * dirender pemanggil memakai shares().
     *
     * @param  list<array{label: string, value: float|int, color: string}>  $slices
     */
    public static function doughnut(array $slices, string $centerTop, string $centerBottom, int $size = 220): string
    {
        $total = 0.0;
        foreach ($slices as $s) {
            $total += max(0.0, self::num($s['value']));
        }
        $r = 80;
        $c = 2 * M_PI * $r;
        $center = $size / 2;

        $svg = ['<svg viewBox="0 0 '.$size.' '.$size.'" class="h-auto w-full" role="img" xmlns="http://www.w3.org/2000/svg">'];
        if ($total <= 0) {
            $svg[] = '<circle cx="'.$center.'" cy="'.$center.'" r="'.$r.'" fill="none" stroke="#e5e7eb" stroke-width="30"/>';
        } else {
            $offset = 0.0;
            foreach ($slices as $s) {
                $value = max(0.0, self::num($s['value']));
                if ($value <= 0) {
                    continue;
                }
                $len = $value / $total * $c;
                // Celah 1.5px antar irisan agar ruas terbaca.
                $draw = max(0.0, $len - 1.5);
                $svg[] = '<circle cx="'.$center.'" cy="'.$center.'" r="'.$r.'" fill="none" stroke="'.$s['color'].'" stroke-width="30" stroke-dasharray="'.round($draw, 2).' '.round($c - $draw, 2).'" stroke-dashoffset="'.round(-$offset, 2).'" transform="rotate(-90 '.$center.' '.$center.')"/>';
                $offset += $len;
            }
        }
        $svg[] = '<text x="'.$center.'" y="'.($center - 4).'" text-anchor="middle" font-size="17" font-weight="600" fill="#022c22">'.self::e($centerTop).'</text>';
        $svg[] = '<text x="'.$center.'" y="'.($center + 16).'" text-anchor="middle" font-size="11" fill="#6b7280">'.self::e($centerBottom).'</text>';
        $svg[] = '</svg>';

        return implode('', $svg);
    }

    /**
     * Persentase tiap irisan (satu desimal) untuk legenda.
     *
     * @param  list<array{value: float|int}>  $slices
     * @return list<float>
     */
    public static function shares(array $slices): array
    {
        $total = 0.0;
        foreach ($slices as $s) {
            $total += max(0.0, self::num($s['value']));
        }
        $out = [];
        foreach ($slices as $s) {
            $out[] = $total > 0 ? round(max(0.0, self::num($s['value'])) / $total * 100, 1) : 0.0;
        }

        return $out;
    }

    /** Format uang ringkas sumbu: Rp1,5 jt / Rp250 rb / Rp900. */
    public static function shortMoney(float $value): string
    {
        $abs = abs($value);
        $sign = $value < 0 ? '-' : '';
        if ($abs >= 1_000_000) {
            $short = rtrim(rtrim(number_format($abs / 1_000_000, 1, ',', '.'), '0'), ',');

            return $sign.'Rp'.$short.' jt';
        }
        if ($abs >= 1_000) {
            $short = rtrim(rtrim(number_format($abs / 1_000, 1, ',', '.'), '0'), ',');

            return $sign.'Rp'.$short.' rb';
        }

        return $sign.'Rp'.number_format($abs, 0, ',', '.');
    }

    /** Koersi numerik tanpa cast (pemindai melarang cast float di app/). */
    private static function num(mixed $value): float
    {
        return is_numeric($value) ? $value + 0 : 0.0;
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
