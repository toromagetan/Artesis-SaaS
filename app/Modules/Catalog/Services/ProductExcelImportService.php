<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use App\Support\XlsxReader;
use App\Support\XlsxWriter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Impor produk Excel/CSV (A4 — persis Artesis, dipagari SaaS).
 *
 * Kontrak Artesis dipertahankan: kolom wajib type, name, sku,
 * purchase_price, selling_price; alias header Indonesia; kategori
 * dipetakan dari type; duplikat SKU di dalam berkas ditolak;
 * semua baris divalidasi SEBELUM transaksi; impor atomik; stok
 * dan transaksi TIDAK diubah (stock_changed=false).
 *
 * Pagar SaaS: pembuatan berjalan lewat ProductService (cap
 * tenant + PlanGate per detail + audit), keberadaan SKU diperiksa
 * PER TENANT di kelima tabel detail (§192/OD-2), aktor platform
 * ditolak. Penyesuaian SaaS: index & variant berupa teks bebas
 * (paritas formulir katalog — tanpa daftar putih Artesis), kolom
 * tambahan barcode, coating, wear_period, expiry_tracked (§193),
 * dan is_process_item.
 */
class ProductExcelImportService
{
    private const LENS_TYPES = ['SV', 'Progresif', 'Bifocal'];

    public function __construct(private readonly ProductService $products) {}

    /**
     * @return array{created: int, rows: int, stock_changed: false, transaction_changed: false}
     */
    public function import(User $actor, UploadedFile $file): array
    {
        $context = TenantContext::for($actor);
        if (! $context->isTenant() && ! $context->isStandalone()) {
            throw new AuthorizationException('Aktor tidak berhak mengimpor produk.');
        }

        $rows = $this->readRows($file);
        if (count($rows) < 2) {
            throw ValidationException::withMessages(['file' => __('ui.product_import.empty')]);
        }

        $required = ['type', 'name', 'sku', 'purchase_price', 'selling_price'];
        $headerRow = array_shift($rows);
        $headers = $this->normalizeHeaders($headerRow ?? []);
        $missing = array_values(array_diff($required, array_values($headers)));
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => __('ui.product_import.missing_columns', ['columns' => implode(', ', $missing)]),
            ]);
        }

        $existingSkus = $this->existingSkus($actor, $context);
        $prepared = [];
        $errors = [];
        $seenSkus = [];
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            if ($this->isBlankRow($row)) {
                continue;
            }
            [$record, $rowErrors] = $this->prepareRow($row, $headers, $rowNumber, $seenSkus, $existingSkus);
            if ($rowErrors !== []) {
                $errors = [...$errors, ...$rowErrors];

                continue;
            }
            $prepared[] = $record;
            $seenSkus[] = strtolower((string) $record['data']['sku']);
        }

        if ($prepared === []) {
            $errors[] = __('ui.product_import.no_rows');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => $errors]);
        }

        $created = DB::transaction(function () use ($actor, $prepared): int {
            foreach ($prepared as $record) {
                $detail = $this->products->createDetail($actor, $record['type'], $record['data'], $record['attributes']);
                if (! $record['is_active']) {
                    $this->products->setDetailActive($actor, $detail, false);
                }
            }

            return count($prepared);
        });

        return ['created' => $created, 'rows' => count($prepared), 'stock_changed' => false, 'transaction_changed' => false];
    }

    /**
     * SKU yang sudah ada pada ember aktor, di kelima tabel detail.
     *
     * @return list<string> sku ternormalisasi (huruf kecil)
     */
    private function existingSkus(User $actor, TenantContext $context): array
    {
        $skus = [];
        foreach (Product::DETAIL_MODELS as $model) {
            $query = $model::withoutGlobalScopes();
            if ($context->isTenant()) {
                $query->where('tenant_id', $actor->tenant_id);
            } else {
                $query->whereNull('tenant_id');
            }
            foreach ($query->pluck('sku') as $sku) {
                $skus[] = strtolower((string) $sku);
            }
        }

        return $skus;
    }

    /**
     * @param  list<array<int, ?string>>  $rows
     * @param  array<int, string>  $headers
     * @param  list<string>  $seenSkus
     * @param  list<string>  $existingSkus
     * @return array{0: ?array{type: string, data: array<string, mixed>, attributes: array<string, string>, is_active: bool}, 1: list<string>}
     */
    private function prepareRow(array $row, array $headers, int $rowNumber, array $seenSkus, array $existingSkus): array
    {
        $value = fn (string $key, mixed $default = null): mixed => $this->value($row, $headers, $key, $default);
        $errors = [];
        $type = $this->normalizeType((string) $value('type', ''));
        $sku = trim((string) $value('sku', ''));
        $name = trim((string) $value('name', ''));

        if ($type === null) {
            $errors[] = __('ui.product_import.row_type', ['row' => $rowNumber]);
        }
        if ($name === '') {
            $errors[] = __('ui.product_import.row_required', ['row' => $rowNumber, 'field' => 'name']);
        } elseif (mb_strlen($name) > 255) {
            $errors[] = __('ui.product_import.row_max', ['row' => $rowNumber, 'field' => 'name']);
        }
        if ($sku === '') {
            $errors[] = __('ui.product_import.row_required', ['row' => $rowNumber, 'field' => 'sku']);
        } elseif (mb_strlen($sku) > 255) {
            $errors[] = __('ui.product_import.row_max', ['row' => $rowNumber, 'field' => 'sku']);
        } elseif (in_array(strtolower($sku), $seenSkus, true) || in_array(strtolower($sku), $existingSkus, true)) {
            $errors[] = __('ui.product_import.row_sku_exists', ['row' => $rowNumber, 'sku' => $sku]);
        }

        $purchasePrice = $this->decimalString($value('purchase_price'));
        $sellingPrice = $this->decimalString($value('selling_price'));
        if ($purchasePrice === null || $this->isNegative($purchasePrice)) {
            $errors[] = __('ui.product_import.row_price', ['row' => $rowNumber, 'field' => 'purchase_price']);
        }
        if ($sellingPrice === null || $this->isNegative($sellingPrice)) {
            $errors[] = __('ui.product_import.row_price', ['row' => $rowNumber, 'field' => 'selling_price']);
        }

        $data = [
            'name' => $name,
            'brand' => $this->nullableString($value('brand')),
            'sku' => $sku,
            'barcode' => $this->nullableString($value('barcode')),
            'cost_price' => $purchasePrice ?? 0,
            'sale_price' => $sellingPrice ?? 0,
        ];
        $attributes = [];

        if ($type !== null) {
            $this->validateTypeSpecific($type, $value, $data, $attributes, $errors, $rowNumber);
        }

        return [
            $errors === [] ? [
                'type' => $type,
                'data' => $data,
                'attributes' => $attributes,
                'is_active' => $this->boolean($value('is_active', true)),
            ] : null,
            $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $attributes
     * @param  list<string>  $errors
     */
    private function validateTypeSpecific(string $type, callable $value, array &$data, array &$attributes, array &$errors, int $rowNumber): void
    {
        if ($type === 'frame') {
            $data['color'] = $this->nullableString($value('color'));

            return;
        }

        if ($type === 'lens') {
            $lensType = trim((string) $value('lens_type', $value('detail_type', '')));
            $eyeSide = trim((string) $value('eye_side', 'Both')) ?: 'Both';
            if (! in_array($lensType, self::LENS_TYPES, true)) {
                $errors[] = __('ui.product_import.row_lens_type', ['row' => $rowNumber]);
            }
            if (! in_array($eyeSide, ['R', 'L', 'Both'], true)) {
                $errors[] = __('ui.product_import.row_eye_side', ['row' => $rowNumber]);
            }
            $data['type'] = $lensType;
            $data['index'] = $this->normalizeIndex($value('index', ''));
            $data['variant'] = $this->variants($value('variant'));
            $data['eye_side'] = $eyeSide;
            foreach (['sph', 'cyl', 'add'] as $field) {
                $number = $this->quarterDecimal($value($field, 0));
                if ($number === null) {
                    $errors[] = __('ui.product_import.row_quarter', ['row' => $rowNumber, 'field' => $field]);
                }
                $data[$field] = $number ?? 0;
            }
            $coating = $this->nullableString($value('coating'));
            if ($coating !== null) {
                $attributes['coating'] = $coating;
            }

            return;
        }

        if ($type === 'rx') {
            $rxType = trim((string) $value('rx_type', $value('detail_type', '')));
            if ($rxType === '') {
                $errors[] = __('ui.product_import.row_required', ['row' => $rowNumber, 'field' => 'rx_type']);
            }
            $data['type'] = $rxType;
            $data['index'] = $this->normalizeIndex($value('index', ''));
            $data['variant'] = $this->variants($value('variant'));

            return;
        }

        if ($type === 'softlens') {
            $data['color'] = $this->nullableString($value('color'));
            $power = $this->quarterDecimal($value('power', 0));
            if ($power === null) {
                $errors[] = __('ui.product_import.row_quarter', ['row' => $rowNumber, 'field' => 'power']);
            }
            $data['power'] = $power ?? 0;
            $data['expiry_tracked'] = $this->booleanOr($value('expiry_tracked'), false);
            $wear = $this->nullableString($value('wear_period'));
            if ($wear !== null) {
                $attributes['wear_period'] = $wear;
            }

            return;
        }

        // accessory
        $data['variant'] = $this->nullableString($value('variant'));
        $data['expiry_tracked'] = $this->booleanOr($value('expiry_tracked'), false);
        $data['is_process_item'] = $this->booleanOr($value('is_process_item'), false);
    }

    /**
     * Baca berkas: XLSX (tanda tangan ZIP) atau teks CSV
     * (pemisah koma/titik-koma mengikuti baris header).
     *
     * @return list<list<?string>>
     */
    private function readRows(UploadedFile $file): array
    {
        $binary = (string) file_get_contents($file->getRealPath());
        if (str_starts_with($binary, "PK\x03\x04")) {
            try {
                return XlsxReader::rows($binary);
            } catch (Throwable) {
                throw ValidationException::withMessages(['file' => __('ui.product_import.unreadable')]);
            }
        }

        $text = preg_replace('/^\xEF\xBB\xBF/', '', $binary) ?? $binary;
        $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];
        $delimiter = ',';
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $delimiter = str_contains($line, ';') && ! str_contains($line, ',') ? ';' : ',';
                break;
            }
        }
        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = array_map(fn ($c) => $c === '' ? null : $c, str_getcsv($line, $delimiter));
        }

        return $rows;
    }

    /**
     * @param  list<?string>  $row
     * @return array<int, string>
     */
    private function normalizeHeaders(array $row): array
    {
        $aliases = [
            'jenis' => 'type', 'tipe' => 'type', 'type_produk' => 'type',
            'merek' => 'brand', 'nama_produk' => 'name', 'nama' => 'name',
            'kode_sku' => 'sku', 'harga_beli' => 'purchase_price', 'hpp' => 'purchase_price',
            'harga_jual' => 'selling_price', 'warna' => 'color',
            'tipe_lensa' => 'lens_type', 'jenis_lensa' => 'lens_type',
            'tipe_rx' => 'rx_type', 'jenis_rx' => 'rx_type',
            'mata' => 'eye_side', 'varian' => 'variant', 'aktif' => 'is_active',
        ];
        $headers = [];
        foreach ($row as $column => $header) {
            $normalized = strtolower(trim((string) $header));
            $normalized = preg_replace('/[^a-z0-9]+/i', '_', $normalized) ?? '';
            $normalized = trim($normalized, '_');
            $headers[$column] = $aliases[$normalized] ?? $normalized;
        }

        return $headers;
    }

    /**
     * @param  list<?string>  $row
     * @param  array<int, string>  $headers
     */
    private function value(array $row, array $headers, string $key, mixed $default = null): mixed
    {
        foreach ($headers as $column => $header) {
            if ($header === $key) {
                return $row[$column] ?? $default;
            }
        }

        return $default;
    }

    private function normalizeType(string $type): ?string
    {
        return match (strtolower(trim($type))) {
            'frame', 'bingkai' => 'frame',
            'lens', 'lensa' => 'lens',
            'rx', 'lensa rx', 'lens rx', 'lensa_rx' => 'rx',
            'softlens', 'soft lens', 'soft_lens' => 'softlens',
            'accessory', 'accessories', 'aksesoris', 'aksesori' => 'accessory',
            default => null,
        };
    }

    /** @return list<string> */
    private function variants(mixed $raw): array
    {
        if ($raw === null || trim((string) $raw) === '') {
            return [];
        }
        $text = trim((string) $raw);
        $decoded = json_decode($text, true);
        $values = is_array($decoded) ? $decoded : preg_split('/\s*[,|;]\s*/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_map(fn ($item): string => trim((string) $item), $values ?: [])));
    }

    /**
     * Angka sebagai STRING desimal ternormalisasi (Blueprint A.5:
     * tanpa cast float di kode aplikasi). Mengembalikan null bila
     * bukan angka. Format ribuan/desimal Indonesia dinormalisasi
     * seperti layanan impor Artesis.
     */
    private function decimalString(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $text = trim((string) $value);
        if (! is_numeric($text)) {
            if (str_contains($text, ',') && str_contains($text, '.')) {
                $text = str_replace('.', '', $text);
                $text = str_replace(',', '.', $text);
            } elseif (substr_count($text, '.') > 1) {
                $text = str_replace('.', '', $text);
            } elseif (str_contains($text, ',')) {
                $text = str_replace(',', '.', $text);
            }
        }

        return preg_match('/^-?\d+(\.\d+)?$/', $text) === 1 ? $text : null;
    }

    private function isNegative(string $decimal): bool
    {
        return str_starts_with($decimal, '-') && preg_match('/[1-9]/', $decimal) === 1;
    }

    /** Kelipatan 0,25 sebagai string dua desimal, atau null. */
    private function quarterDecimal(mixed $value): ?string
    {
        $text = $this->decimalString($value);
        if ($text === null) {
            return null;
        }
        $negative = str_starts_with($text, '-');
        $unsigned = ltrim($text, '-');
        [$intPart, $fracPart] = array_pad(explode('.', $unsigned, 2), 2, '');
        $frac4 = (int) str_pad(substr($fracPart, 0, 4), 4, '0');
        if (strlen($fracPart) > 4 && (int) substr($fracPart, 4) > 0) {
            return null;
        }
        if ($frac4 % 2500 !== 0) {
            return null;
        }
        $frac2 = str_pad((string) intdiv($frac4, 100), 2, '0', STR_PAD_LEFT);
        $intPart = ltrim($intPart, '0') ?: '0';
        $sign = $negative && ($intPart !== '0' || $frac2 !== '00') ? '-' : '';

        return $sign.$intPart.'.'.$frac2;
    }

    private function normalizeIndex(mixed $value): string
    {
        $text = $this->decimalString($value);
        if ($text === null) {
            return trim((string) $value);
        }
        [$intPart, $fracPart] = array_pad(explode('.', ltrim($text, '-'), 2), 2, '');
        $frac2 = str_pad(substr($fracPart, 0, 2), 2, '0');

        return (str_starts_with($text, '-') ? '-' : '').(ltrim($intPart, '0') ?: '0').'.'.$frac2;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    /** Boolean dengan bawaan eksplisit untuk sel kosong/absen. */
    private function booleanOr(mixed $value, bool $default): bool
    {
        if ($value === null || trim((string) $value) === '') {
            return $default;
        }

        return $this->boolean($value);
    }

    private function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return ! in_array(strtolower(trim((string) $value)), ['0', 'false', 'no', 'tidak', 'nonaktif'], true);
    }

    /** @param  list<?string>  $row */
    private function isBlankRow(array $row): bool
    {
        return collect($row)->filter(fn ($value): bool => trim((string) $value) !== '')->isEmpty();
    }

    /** Isi template XLSX (header + contoh tiga tipe). */
    public function templateXlsx(): string
    {
        $header = ['type', 'brand', 'name', 'sku', 'purchase_price', 'selling_price', 'barcode', 'color', 'lens_type', 'index', 'variant', 'eye_side', 'sph', 'cyl', 'add', 'rx_type', 'power', 'wear_period', 'coating', 'expiry_tracked', 'is_process_item', 'is_active'];
        $rows = [
            $header,
            ['frame', 'RayBan', 'Aviator Classic', 'FRM-001', 450000, 750000, '', 'Gold', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['lens', 'Essilor', 'FSV 1.56 Bluecut', 'LNS-001', 120000, 250000, '', '', 'SV', '1.56', 'Bluecut', 'Both', -1.00, -0.50, '', '', '', '', 'Bluecut', '', '', ''],
            ['softlens', 'X2', 'X2 Bio Color', 'SFT-001', 45000, 85000, '', 'Brown', '', '', '', '', '', '', '', '', -2.00, 'Monthly', '', 1, '', ''],
        ];

        return XlsxWriter::make([['title' => 'Template Import', 'rows' => $rows]]);
    }
}
