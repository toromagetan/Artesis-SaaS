<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Pembaca XLSX murni PHP — pasangan XlsxWriter (tanpa pustaka
 * luar). Membaca sheet PERTAMA: string bersama (sharedStrings),
 * string inline, angka, dan boolean. Cukup untuk berkas template
 * datar hasil Excel/LibreOffice maupun keluaran XlsxWriter.
 */
class XlsxReader
{
    /**
     * @return list<list<?string>> baris-baris sheet pertama
     */
    public static function rows(string $binary): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'lentera-xlsx-read-');
        if ($tmp === false) {
            throw new RuntimeException('Tidak dapat membuat berkas sementara XLSX.');
        }
        file_put_contents($tmp, $binary);

        try {
            $zip = new ZipArchive;
            if ($zip->open($tmp) !== true) {
                throw new RuntimeException('Berkas XLSX tidak dapat dibuka.');
            }

            $shared = [];
            $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
            if ($sharedXml !== false) {
                $sx = simplexml_load_string($sharedXml);
                if ($sx !== false) {
                    foreach ($sx->children() as $si) {
                        $text = '';
                        foreach ($si->xpath('.//*[local-name()="t"]') ?: [] as $node) {
                            $text .= (string) $node;
                        }
                        $shared[] = $text;
                    }
                }
            }

            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXml === false) {
                // Cari sheet pertama dengan nama lain.
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    if ($name !== false && preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name) === 1) {
                        $sheetXml = $zip->getFromName($name);
                        break;
                    }
                }
            }
            $zip->close();
            if ($sheetXml === false) {
                throw new RuntimeException('Sheet XLSX tidak ditemukan.');
            }

            $sheet = simplexml_load_string($sheetXml);
            if ($sheet === false) {
                throw new RuntimeException('Sheet XLSX tidak dapat dibaca.');
            }

            $rows = [];
            foreach ($sheet->xpath('.//*[local-name()="row"]') ?: [] as $node) {
                $cells = [];
                foreach ($node->children() as $c) {
                    if ($c->getName() !== 'c') {
                        continue;
                    }
                    $ref = (string) ($c->attributes()['r'] ?? '');
                    $colIndex = self::columnIndex($ref);
                    $type = (string) ($c->attributes()['t'] ?? '');
                    $value = null;
                    if ($type === 'inlineStr') {
                        $text = '';
                        foreach ($c->xpath('.//*[local-name()="t"]') ?: [] as $inner) {
                            $text .= (string) $inner;
                        }
                        $value = $text;
                    } else {
                        $raw = null;
                        foreach ($c->children() as $child) {
                            if ($child->getName() === 'v') {
                                $raw = (string) $child;
                                break;
                            }
                        }
                        if ($raw !== null) {
                            $value = $type === 's' ? ($shared[(int) $raw] ?? '') : $raw;
                        }
                    }
                    while (count($cells) < $colIndex) {
                        $cells[] = null;
                    }
                    $cells[] = $value;
                }
                $rows[] = $cells;
            }

            return $rows;
        } finally {
            @unlink($tmp);
        }
    }

    private static function columnIndex(string $ref): int
    {
        if (preg_match('/^([A-Z]+)\d+$/', strtoupper($ref), $m) !== 1) {
            return 0;
        }
        $index = 0;
        foreach (str_split($m[1]) as $ch) {
            $index = $index * 26 + (ord($ch) - 64);
        }

        return $index - 1;
    }
}
