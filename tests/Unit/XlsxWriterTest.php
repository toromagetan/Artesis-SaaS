<?php

namespace Tests\Unit;

use App\Support\XlsxWriter;
use Tests\TestCase;
use ZipArchive;

/**
 * XlsxWriter: arsip OOXML sah — bagian wajib ada, angka menjadi
 * sel numerik, string inline ter-escape, banyak sheet.
 */
class XlsxWriterTest extends TestCase
{
    private function unzip(string $bytes): ZipArchive
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx-test-');
        file_put_contents($tmp, $bytes);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp));

        return $zip;
    }

    public function test_workbook_structure_numbers_and_escaping(): void
    {
        $bytes = XlsxWriter::make([
            ['title' => 'Laporan', 'rows' => [
                ['Cabang', 'Total'],
                ['Pusat & "Utama"', '150000.50'],
                ['Cabang <Dua>', 42],
            ]],
            ['title' => 'Kedua', 'rows' => [['X']]],
        ]);
        $this->assertStringStartsWith('PK', $bytes);

        $zip = $this->unzip($bytes);
        $this->assertNotFalse($zip->getFromName('[Content_Types].xml'));
        $this->assertNotFalse($zip->getFromName('xl/workbook.xml'));
        $sheet1 = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $sheet2 = (string) $zip->getFromName('xl/worksheets/sheet2.xml');
        $zip->close();

        // Angka → sel numerik; string → inline ter-escape.
        $this->assertStringContainsString('<c r="B2"><v>150000.50</v></c>', $sheet1);
        $this->assertStringContainsString('<c r="B3"><v>42</v></c>', $sheet1);
        $this->assertStringContainsString('Pusat &amp; &quot;Utama&quot;', $sheet1);
        $this->assertStringContainsString('Cabang &lt;Dua&gt;', $sheet1);
        $this->assertStringContainsString('X', $sheet2);
    }

    public function test_empty_rows_and_long_titles_are_safe(): void
    {
        $bytes = XlsxWriter::make([['title' => str_repeat('A', 60), 'rows' => []]]);
        $zip = $this->unzip($bytes);
        $workbook = (string) $zip->getFromName('xl/workbook.xml');
        $zip->close();
        $this->assertStringContainsString(str_repeat('A', 31), $workbook);
        $this->assertStringNotContainsString(str_repeat('A', 32), $workbook);
    }
}
