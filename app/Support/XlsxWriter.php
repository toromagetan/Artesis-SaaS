<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Penulis XLSX murni PHP (preseden Barcode/SvgChart: tanpa
 * pustaka luar). Menghasilkan workbook OOXML minimal yang sah:
 * string inline (tanpa sharedStrings), angka sebagai sel
 * numerik, banyak sheet. Cukup untuk ekspor laporan & matriks.
 */
class XlsxWriter
{
    /**
     * @param  array<int, array{title: string, rows: array<int, array<int, mixed>>}>  $sheets
     * @return string biner berkas .xlsx
     */
    public static function make(array $sheets): string
    {
        if ($sheets === []) {
            $sheets = [['title' => 'Sheet1', 'rows' => []]];
        }
        $tmp = tempnam(sys_get_temp_dir(), 'lentera-xlsx-');
        if ($tmp === false) {
            throw new RuntimeException('Tidak dapat membuat berkas sementara XLSX.');
        }
        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat membuka arsip XLSX.');
        }

        $contentTypes = ['<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">',
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>',
            '<Default Extension="xml" ContentType="application/xml"/>',
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'];
        $workbookSheets = [];
        $workbookRels = [];
        foreach ($sheets as $i => $sheet) {
            $n = $i + 1;
            $contentTypes[] = '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $workbookSheets[] = '<sheet name="'.self::e(self::sheetTitle($sheet['title'] ?? 'Sheet'.$n)).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
            $workbookRels[] = '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';
            $zip->addFromString('xl/worksheets/sheet'.$n.'.xml', self::sheetXml($sheet['rows'] ?? []));
        }
        $contentTypes[] = '</Types>';

        $zip->addFromString('[Content_Types].xml', implode('', $contentTypes));
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.implode('', $workbookSheets).'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.implode('', $workbookRels).'</Relationships>');
        $zip->close();

        $bytes = (string) file_get_contents($tmp);
        unlink($tmp);

        return $bytes;
    }

    private static function sheetTitle(string $title): string
    {
        $title = trim(str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $title));

        return $title === '' ? 'Sheet' : substr($title, 0, 31);
    }

    private static function sheetXml(array $rows): string
    {
        $out = ['<?xml version="1.0" encoding="UTF-8" standalone="yes"?>',
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'];
        $r = 0;
        foreach ($rows as $row) {
            $r++;
            $out[] = '<row r="'.$r.'">';
            $c = 0;
            foreach ($row as $value) {
                $c++;
                $ref = self::column($c).$r;
                if ($value === null || $value === '') {
                    continue;
                }
                if (is_numeric($value)) {
                    $out[] = '<c r="'.$ref.'"><v>'.$value.'</v></c>';
                } else {
                    $out[] = '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.self::e((string) $value).'</t></is></c>';
                }
            }
            $out[] = '</row>';
        }
        $out[] = '</sheetData></worksheet>';

        return implode('', $out);
    }

    private static function column(int $index): string
    {
        $letters = '';
        while ($index > 0) {
            $index--;
            $letters = chr(65 + ($index % 26)).$letters;
            $index = intdiv($index, 26);
        }

        return $letters;
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
