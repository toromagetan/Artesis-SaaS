<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Events\InitialStockImported;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Impor stok awal (Blueprint D.8 + PRD 7.5 + uji G.1.4).
 *
 * Alur dua tahap:
 *  1. preview(csvContent) — validasi PENUH tanpa menulis apa pun:
 *     SKU harus ada & aktif; cabang terurai dari ID atau Nama persis
 *     (branches tidak punya kolom kode); qty bilangan bulat ≥ 0;
 *     duplikat (SKU, cabang) di dalam berkas = kesalahan baris.
 *     Hasil + token disimpan di cache 30 menit.
 *  2. commit(token) — pratinjau WAJIB bersih (satu baris rusak pun
 *     menggagalkan seluruh komitmen: nol baris terimpor); cabang yang
 *     sudah pernah diimpor DITOLAK (kunci: gerakan initial_stock
 *     ber-reference initial_stock_import di buku besar); semua baris
 *     terbit sebagai gerakan initial_stock dalam SATU transaksi.
 *
 * Format berkas: CSV dengan header `sku,cabang,qty` (cabang = ID
 * numerik atau nama persis). Template yang diunduh memakai format ini
 * dan dapat dibuka/disimpan dari Excel.
 */
class InitialStockImportService
{
    public const REFERENCE_TYPE = 'initial_stock_import';

    private const CACHE_PREFIX = 'initial-stock-preview:';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly InventoryService $inventory,
    ) {}

    /** Cabang yang sudah memiliki komitmen impor (tidak dapat diimpor ulang). */
    public function branchAlreadyImported(int $branchId): bool
    {
        return StockMovement::withoutGlobalScopes()
            ->where('branch_id', $branchId)
            ->where('reason', 'initial_stock')
            ->where('reference_type', self::REFERENCE_TYPE)
            ->exists();
    }

    /**
     * @return array{token: string, valid: array<int, array{line: int, sku: string, product_id: int, branch_id: int, branch_name: string, qty: int}>, errors: array<int, array{line: int, message: string}>}
     */
    public function preview(string $csvContent): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent)) ?: [];
        $rows = [];
        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = ['line' => $i + 1, 'cells' => str_getcsv($line)];
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['file' => __('ui.import.empty_file')]);
        }

        // Baris pertama = header.
        $header = array_map(fn ($c) => strtolower(trim((string) $c)), $rows[0]['cells']);
        if ($header !== ['sku', 'cabang', 'qty']) {
            throw ValidationException::withMessages(['file' => __('ui.import.header_invalid')]);
        }
        array_shift($rows);

        $products = Product::where('is_active', true)->get()->keyBy('sku');
        $branchesById = Branch::all()->keyBy('id');
        $branchesByName = Branch::all()->keyBy(fn (Branch $b) => mb_strtolower(trim($b->name)));

        $valid = [];
        $errors = [];
        $seen = [];
        foreach ($rows as $row) {
            $lineNo = $row['line'];
            [$sku, $branchCell, $qtyCell] = array_map(
                fn ($c) => trim((string) $c),
                $row['cells'] + [0 => '', 1 => '', 2 => ''],
            );

            $product = $products->get($sku);
            if ($product === null) {
                $errors[] = ['line' => $lineNo, 'message' => __('ui.import.sku_unknown', ['sku' => $sku])];

                continue;
            }
            $branch = null;
            if (ctype_digit($branchCell)) {
                $branch = $branchesById->get((int) $branchCell);
            }
            $branch ??= $branchesByName->get(mb_strtolower($branchCell));
            if ($branch === null) {
                $errors[] = ['line' => $lineNo, 'message' => __('ui.import.branch_unknown', ['branch' => $branchCell])];

                continue;
            }
            if (! ctype_digit($qtyCell)) {
                $errors[] = ['line' => $lineNo, 'message' => __('ui.import.qty_invalid', ['qty' => $qtyCell])];

                continue;
            }
            $key = $product->id.':'.$branch->id;
            if (isset($seen[$key])) {
                $errors[] = ['line' => $lineNo, 'message' => __('ui.import.duplicate_row', ['sku' => $sku])];

                continue;
            }
            $seen[$key] = true;
            if ($this->branchAlreadyImported($branch->id)) {
                $errors[] = ['line' => $lineNo, 'message' => __('ui.import.branch_done', ['branch' => $branch->name])];

                continue;
            }
            $valid[] = [
                'line' => $lineNo,
                'sku' => $sku,
                'product_id' => $product->id,
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'qty' => (int) $qtyCell,
            ];
        }

        $token = (string) Str::uuid();
        Cache::put(self::CACHE_PREFIX.$token, ['valid' => $valid, 'errors' => $errors], now()->addMinutes(30));

        return ['token' => $token, 'valid' => $valid, 'errors' => $errors];
    }

    /** @return array{token: string, valid: array, errors: array}|null */
    public function getPreview(string $token): ?array
    {
        $data = Cache::get(self::CACHE_PREFIX.$token);

        return $data === null ? null : ['token' => $token] + $data;
    }

    /**
     * Komitmen impor dari pratinjau bersih. Mengembalikan peta
     * branch_id => total nilai (untuk jurnal & laporan).
     *
     * @return array<int, string>
     */
    public function commit(User $actor, string $token): array
    {
        $preview = $this->getPreview($token);
        if ($preview === null) {
            throw ValidationException::withMessages(['token' => __('ui.import.token_expired')]);
        }
        // Uji G.1.4: pratinjau kotor → nol baris terimpor.
        if ($preview['errors'] !== []) {
            throw ValidationException::withMessages(['token' => __('ui.import.preview_dirty')]);
        }
        $rows = array_values(array_filter($preview['valid'], fn ($r) => $r['qty'] > 0));
        if ($rows === []) {
            throw ValidationException::withMessages(['token' => __('ui.import.nothing_to_import')]);
        }

        $result = DB::transaction(function () use ($actor, $rows) {
            // Periksa ulang di dalam transaksi: cabang belum pernah diimpor.
            $branchIds = array_unique(array_column($rows, 'branch_id'));
            foreach ($branchIds as $branchId) {
                if ($this->branchAlreadyImported((int) $branchId)) {
                    throw ValidationException::withMessages([
                        'token' => __('ui.import.branch_done', ['branch' => Branch::find($branchId)?->name ?? '#'.$branchId]),
                    ]);
                }
            }

            // Jangkar batch: satu baris audit per komitmen menjadi
            // reference_id semua gerakan impor ini.
            $anchor = $this->audit->log(action: 'stock.import_initial', subjectType: 'initial_stock_import',
                newValues: ['rows' => count($rows), 'branches' => $branchIds], actor: $actor);

            $totals = [];
            foreach ($rows as $row) {
                $product = Product::findOrFail($row['product_id']);
                $branch = Branch::findOrFail($row['branch_id']);
                $this->inventory->recordMovement(
                    $actor, $product, $branch, 'initial_stock',
                    $row['qty'], self::REFERENCE_TYPE, $anchor->id,
                );
                $value = Money::fromDecimal((string) $product->cost_price)->mul($row['qty']);
                $totals[$row['branch_id']] = isset($totals[$row['branch_id']])
                    ? $totals[$row['branch_id']]->add($value)
                    : $value;
            }

            foreach ($totals as $branchId => $total) {
                InitialStockImported::dispatch(Branch::findOrFail($branchId), $total->toDecimalString(), $anchor->id);
            }

            return collect($totals)->map(fn (Money $m) => $m->toDecimalString())->all();
        });

        Cache::forget(self::CACHE_PREFIX.$token);

        return $result;
    }

    /** Isi berkas template CSV (dibuka Excel): petunjuk sebagai baris komentar tidak dipakai — header + contoh. */
    public function templateCsv(): string
    {
        $branch = Branch::orderBy('id')->first();
        $product = Product::where('is_active', true)->orderBy('id')->first();
        $lines = ['sku,cabang,qty'];
        if ($product !== null && $branch !== null) {
            $lines[] = $product->sku.','.$branch->id.',10';
        }

        return implode("\n", $lines)."\n";
    }
}
