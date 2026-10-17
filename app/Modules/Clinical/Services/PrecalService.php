<?php

namespace App\Modules\Clinical\Services;

use App\Modules\Clinical\Models\Prescription;

/**
 * Precal (Blueprint D.5 + PRD Detail 3.4) — turunan baca-saja dari resep:
 * 11 parameter per mata. ATURAN MUTLAK OWNER: struktur hasil DILARANG
 * memuat PDR/PDL/PD dalam nama kunci maupun nilai — PD pasien hanya
 * hidup di resep; di sini PD hanya dipakai internal untuk menurunkan
 * desentrasi & diameter blank minimum, tidak pernah dikeluarkan.
 *
 * Rumus turunan (estimasi terdokumentasi):
 * - daya total mata (ekuivalen sferis): SE = SPH + CYL/2
 * - daya efektif vertex (jarak vertex 12 mm): F' = SE / (1 − 0,012·SE)
 * - ketebalan tepi estimasi: sag = ((D/2)²·|SE|) / (2000·(n−1)),
 *   D = 65 mm, n = 1,56; lensa minus: 1,0 + sag; plus: maks(0,7; 2,2 − sag)
 * - desentrasi per mata: setengah jarak pusat frame baku (70/2 = 35 mm)
 *   dikurangi PD mata pasien (atau pd_total/2)
 * - diameter blank minimum: 2·|desentrasi| + diameter efektif baku 52 mm
 * - kelayakan RX: SPH dalam [−12, +8] dan |CYL| ≤ 6
 * - ringkasan pasangan: |SE OD − SE OS| ≤ 0,50 → 'balanced', selain itu
 *   'anisometropia'
 */
class PrecalService
{
    private const VERTEX_DISTANCE = 0.012;

    private const LENS_INDEX = 1.56;

    private const BLANK_DIAMETER = 65.0;

    private const FRAME_CENTER_DISTANCE = 70.0;

    private const EFFECTIVE_DIAMETER = 52.0;

    /**
     * @return array{od: array<string, mixed>, os: array<string, mixed>, assumptions: array<string, mixed>}
     */
    public function compute(Prescription $prescription): array
    {
        $od = $this->computeEye($prescription, 'od');
        $os = $this->computeEye($prescription, 'os');

        $pairSummary = abs($od['total_eye_power'] - $os['total_eye_power']) <= 0.50
            ? 'balanced'
            : 'anisometropia';
        $od['pair_summary'] = $pairSummary;
        $os['pair_summary'] = $pairSummary;

        return [
            'od' => $od,
            'os' => $os,
            'assumptions' => [
                'lens_index' => self::LENS_INDEX,
                'blank_diameter_mm' => self::BLANK_DIAMETER,
                'frame_center_distance_mm' => self::FRAME_CENTER_DISTANCE,
                'effective_diameter_mm' => self::EFFECTIVE_DIAMETER,
                'vertex_distance_mm' => self::VERTEX_DISTANCE * 1000,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function computeEye(Prescription $prescription, string $side): array
    {
        $eye = $prescription->eye($side);
        $sph = $eye['sph'] + 0;
        $cyl = $eye['cyl'] + 0;

        $totalPower = round($sph + $cyl / 2, 2);
        $vertexPower = round($totalPower / (1 - self::VERTEX_DISTANCE * $totalPower), 2);

        $sag = (($this->blankRadius() ** 2) * abs($totalPower)) / (2000 * (self::LENS_INDEX - 1));
        $edge = $totalPower < 0
            ? round(1.0 + $sag, 2)
            : round(max(0.7, 2.2 - $sag), 2);

        $decentration = $this->decentration($prescription, $side, $eye['pd']);
        $minBlank = $decentration !== null
            ? round(2 * abs($decentration) + self::EFFECTIVE_DIAMETER, 1)
            : null;

        return [
            'sph' => $eye['sph'],
            'cyl' => $eye['cyl'],
            'axis' => $eye['axis'],
            'add' => $eye['add'],
            'vertex_effective_power' => $vertexPower,
            'estimated_edge_thickness_mm' => $edge,
            'minimum_blank_diameter_mm' => $minBlank,
            'decentration_mm' => $decentration,
            'total_eye_power' => $totalPower,
            'rx_feasibility' => ($sph >= -12.0 && $sph <= 8.0 && abs($cyl) <= 6.0) ? 'within_range' : 'outside_range',
            'pair_summary' => null, // diisi compute() sesudah kedua mata dihitung
        ];
    }

    private function blankRadius(): float
    {
        return self::BLANK_DIAMETER / 2;
    }

    private function decentration(Prescription $prescription, string $side, ?string $eyePd): ?float
    {
        $pd = $eyePd !== null ? $eyePd + 0 : null;
        if ($pd === null && $prescription->pd_total !== null) {
            $pd = $prescription->pd_total / 2;
        }
        if ($pd === null) {
            return null;
        }

        return round(self::FRAME_CENTER_DISTANCE / 2 - $pd, 1);
    }
}
