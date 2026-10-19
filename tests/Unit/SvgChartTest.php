<?php

namespace Tests\Unit;

use App\Support\SvgChart;
use PHPUnit\Framework\TestCase;

/**
 * SvgChart: geometri grafik SVG server-side — titik di dalam
 * kanvas, irisan doughnut menutup lingkaran, format sumbu ringkas.
 */
class SvgChartTest extends TestCase
{
    public function test_line_chart_points_stay_inside_canvas(): void
    {
        $svg = SvgChart::line(['a', 'b', 'c'], [
            ['label' => 'S', 'color' => '#059669', 'points' => [0, 100000, 50000]],
        ], 680, 250);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('<polyline', $svg);
        preg_match_all('/points="([^"]+)"/', $svg, $m);
        foreach (explode(' ', $m[1][0]) as $pair) {
            [$x, $y] = array_map('floatval', explode(',', $pair));
            $this->assertGreaterThanOrEqual(0, $x);
            $this->assertLessThanOrEqual(680, $x);
            $this->assertGreaterThanOrEqual(0, $y);
            $this->assertLessThanOrEqual(250, $y);
        }
        // Label berbahaya dilolos-escape.
        $evil = SvgChart::line(['<b>x</b>'], [['label' => 'S', 'color' => '#000000', 'points' => [1]]]);
        $this->assertStringNotContainsString('<b>x</b>', $evil);
        $this->assertStringContainsString('&lt;b&gt;', $evil);
    }

    public function test_doughnut_slices_cover_the_circle_and_shares_sum_to_100(): void
    {
        $slices = [
            ['label' => 'A', 'value' => 300, 'color' => '#059669'],
            ['label' => 'B', 'value' => 100, 'color' => '#c9a227'],
        ];
        $svg = SvgChart::doughnut($slices, 'Rp400', 'Total');
        $this->assertSame(2, substr_count($svg, 'stroke-dasharray'));
        $shares = SvgChart::shares($slices);
        $this->assertEqualsWithDelta(100.0, array_sum($shares), 0.2);
        $this->assertSame(75.0, $shares[0]);

        $empty = SvgChart::doughnut([], '—', 'Total');
        $this->assertStringContainsString('#e5e7eb', $empty);
    }

    public function test_short_money_format(): void
    {
        $this->assertSame('Rp1,5 jt', SvgChart::shortMoney(1_500_000));
        $this->assertSame('Rp2 jt', SvgChart::shortMoney(2_000_000));
        $this->assertSame('Rp250 rb', SvgChart::shortMoney(250_000));
        $this->assertSame('Rp900', SvgChart::shortMoney(900));
        $this->assertSame('Rp0', SvgChart::shortMoney(0));
    }
}
