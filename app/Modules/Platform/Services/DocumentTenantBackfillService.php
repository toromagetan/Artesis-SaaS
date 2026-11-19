<?php

namespace App\Modules\Platform\Services;

use Illuminate\Support\Facades\DB;

/**
 * Isi ulang tenant_id dokumen bisnis (Keputusan C PR-1,
 * keputusan owner 2026-11-18): memetakan setiap dokumen lama ke
 * tenant pemiliknya HANYA dari sumber kepemilikan terverifikasi
 * (rancangan teknis §2):
 *
 * - sales, sale_returns, purchase_requests, purchase_orders,
 *   warranty_cases, customer_credits: cabang dokumen.
 * - receipts: PO -> cabang PO. purchase_returns: penerimaan ->
 *   PO -> cabang PO.
 * - purchase_payments: alokasi -> penerimaan -> PO -> cabang;
 *   tanpa alokasi = TAK TERPETA; alokasi menunjuk >1 tenant =
 *   AMBIGU. Keduanya dilaporkan dan DIBIARKAN NULL — tidak
 *   pernah ditebak.
 * - stock_transfers: cabang asal & tujuan harus satu tenant;
 *   berbeda = AMBIGU, dilaporkan, dibiarkan NULL.
 * - journals: hanya diverifikasi (sudah dicap sejak 000055;
 *   NULL di sana adalah ember mandiri yang sah).
 *
 * Idempoten: hanya baris ber-tenant_id NULL yang diproses;
 * baris yang sumbernya memang bertenant NULL dihitung sebagai
 * ember MANDIRI (keadaan akhir yang benar, bukan temuan).
 */
class DocumentTenantBackfillService
{
    /**
     * @return array<string, array{total: int, sudah: int, terpetakan: int, mandiri: int, ambigu: int, tak_terpeta: int}>
     */
    public function run(bool $verify = false): array
    {
        $report = [];
        foreach (['sales', 'sale_returns', 'purchase_requests', 'purchase_orders', 'warranty_cases', 'customer_credits'] as $table) {
            $report[$table] = $this->viaBranch($table, 'branch_id', $verify);
        }
        $report['receipts'] = $this->viaPurchaseOrderBranch('receipts', 'purchase_order_id', $verify);
        $report['purchase_returns'] = $this->viaReceiptPurchaseOrderBranch($verify);
        $report['purchase_payments'] = $this->payments($verify);
        $report['stock_transfers'] = $this->transfers($verify);
        $report['journals'] = $this->journalsOnly();

        return $report;
    }

    /** @param  array<string, array<string, int>>  $report */
    public static function hasFindings(array $report): bool
    {
        foreach ($report as $row) {
            if (($row['ambigu'] ?? 0) > 0 || ($row['tak_terpeta'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /** @return array{total: int, sudah: int, terpetakan: int, mandiri: int, ambigu: int, tak_terpeta: int} */
    private function viaBranch(string $table, string $branchColumn, bool $verify): array
    {
        $stats = $this->baseStats($table);

        $resolvable = DB::table("{$table} as d")
            ->join('branches as b', "d.{$branchColumn}", '=', 'b.id')
            ->whereNull('d.tenant_id')->whereNotNull('b.tenant_id');
        $stats['terpetakan'] = (clone $resolvable)->count();
        if (! $verify && $stats['terpetakan'] > 0) {
            (clone $resolvable)->update(['d.tenant_id' => DB::raw('b.tenant_id')]);
        }

        $stats['mandiri'] = DB::table("{$table} as d")
            ->join('branches as b', "d.{$branchColumn}", '=', 'b.id')
            ->whereNull('d.tenant_id')->whereNull('b.tenant_id')->count();
        $stats['tak_terpeta'] = $this->remaining($stats);

        return $stats;
    }

    /** @return array{total: int, sudah: int, terpetakan: int, mandiri: int, ambigu: int, tak_terpeta: int} */
    private function viaPurchaseOrderBranch(string $table, string $poColumn, bool $verify): array
    {
        $stats = $this->baseStats($table);

        $resolvable = DB::table("{$table} as d")
            ->join('purchase_orders as po', "d.{$poColumn}", '=', 'po.id')
            ->join('branches as b', 'po.branch_id', '=', 'b.id')
            ->whereNull('d.tenant_id')->whereNotNull('b.tenant_id');
        $stats['terpetakan'] = (clone $resolvable)->count();
        if (! $verify && $stats['terpetakan'] > 0) {
            (clone $resolvable)->update(['d.tenant_id' => DB::raw('b.tenant_id')]);
        }

        $stats['mandiri'] = DB::table("{$table} as d")
            ->join('purchase_orders as po', "d.{$poColumn}", '=', 'po.id')
            ->join('branches as b', 'po.branch_id', '=', 'b.id')
            ->whereNull('d.tenant_id')->whereNull('b.tenant_id')->count();
        $stats['tak_terpeta'] = $this->remaining($stats);

        return $stats;
    }

    /** @return array{total: int, sudah: int, terpetakan: int, mandiri: int, ambigu: int, tak_terpeta: int} */
    private function viaReceiptPurchaseOrderBranch(bool $verify): array
    {
        $stats = $this->baseStats('purchase_returns');

        $resolvable = DB::table('purchase_returns as d')
            ->join('receipts as r', 'd.receipt_id', '=', 'r.id')
            ->join('purchase_orders as po', 'r.purchase_order_id', '=', 'po.id')
            ->join('branches as b', 'po.branch_id', '=', 'b.id')
            ->whereNull('d.tenant_id')->whereNotNull('b.tenant_id');
        $stats['terpetakan'] = (clone $resolvable)->count();
        if (! $verify && $stats['terpetakan'] > 0) {
            (clone $resolvable)->update(['d.tenant_id' => DB::raw('b.tenant_id')]);
        }

        $stats['mandiri'] = DB::table('purchase_returns as d')
            ->join('receipts as r', 'd.receipt_id', '=', 'r.id')
            ->join('purchase_orders as po', 'r.purchase_order_id', '=', 'po.id')
            ->join('branches as b', 'po.branch_id', '=', 'b.id')
            ->whereNull('d.tenant_id')->whereNull('b.tenant_id')->count();
        $stats['tak_terpeta'] = $this->remaining($stats);

        return $stats;
    }

    /** @return array{total: int, sudah: int, terpetakan: int, mandiri: int, ambigu: int, tak_terpeta: int} */
    private function payments(bool $verify): array
    {
        $stats = $this->baseStats('purchase_payments');

        $pendingIds = DB::table('purchase_payments')->whereNull('tenant_id')->pluck('id');
        foreach ($pendingIds as $paymentId) {
            $allocationCount = DB::table('purchase_payment_allocations')
                ->where('purchase_payment_id', $paymentId)->count();
            if ($allocationCount === 0) {
                $stats['tak_terpeta']++;

                continue;
            }
            $tenants = DB::table('purchase_payment_allocations as a')
                ->join('receipts as r', 'a.receipt_id', '=', 'r.id')
                ->join('purchase_orders as po', 'r.purchase_order_id', '=', 'po.id')
                ->join('branches as b', 'po.branch_id', '=', 'b.id')
                ->where('a.purchase_payment_id', $paymentId)
                ->distinct()->pluck('b.tenant_id')
                ->map(fn ($t) => $t !== null ? (int) $t : null)->all();
            $distinct = array_values(array_unique($tenants));
            if (count($distinct) > 1) {
                $stats['ambigu']++;
            } elseif (count($distinct) === 1 && $distinct[0] !== null) {
                $stats['terpetakan']++;
                if (! $verify) {
                    DB::table('purchase_payments')->where('id', $paymentId)
                        ->update(['tenant_id' => $distinct[0]]);
                }
            } else {
                $stats['mandiri']++;
            }
        }

        return $stats;
    }

    /** @return array{total: int, sudah: int, terpetakan: int, mandiri: int, ambigu: int, tak_terpeta: int} */
    private function transfers(bool $verify): array
    {
        $stats = $this->baseStats('stock_transfers');

        $resolvable = DB::table('stock_transfers as d')
            ->join('branches as bf', 'd.from_branch_id', '=', 'bf.id')
            ->join('branches as bt', 'd.to_branch_id', '=', 'bt.id')
            ->whereNull('d.tenant_id')->whereNotNull('bf.tenant_id')
            ->whereColumn('bf.tenant_id', 'bt.tenant_id');
        $stats['terpetakan'] = (clone $resolvable)->count();
        if (! $verify && $stats['terpetakan'] > 0) {
            (clone $resolvable)->update(['d.tenant_id' => DB::raw('bf.tenant_id')]);
        }

        $stats['mandiri'] = DB::table('stock_transfers as d')
            ->join('branches as bf', 'd.from_branch_id', '=', 'bf.id')
            ->join('branches as bt', 'd.to_branch_id', '=', 'bt.id')
            ->whereNull('d.tenant_id')->whereNull('bf.tenant_id')->whereNull('bt.tenant_id')->count();
        // Sisa baris NULL sesudah dua kategori di atas = asal dan
        // tujuan tidak sepakat soal tenant: temuan, bukan tebakan.
        $stats['ambigu'] = $this->remaining($stats);

        return $stats;
    }

    /** @return array{total: int, sudah: int, terpetakan: int, mandiri: int, ambigu: int, tak_terpeta: int} */
    private function journalsOnly(): array
    {
        $stats = $this->baseStats('journals');
        // journals tidak diisi ulang: NULL di sini adalah ember
        // mandiri yang sah sejak 000055.
        $stats['mandiri'] = $stats['total'] - $stats['sudah'];

        return $stats;
    }

    /** @return array{total: int, sudah: int, terpetakan: int, mandiri: int, ambigu: int, tak_terpeta: int} */
    private function baseStats(string $table): array
    {
        return [
            'total' => DB::table($table)->count(),
            'sudah' => DB::table($table)->whereNotNull('tenant_id')->count(),
            'terpetakan' => 0,
            'mandiri' => 0,
            'ambigu' => 0,
            'tak_terpeta' => 0,
        ];
    }

    /** @param  array{total: int, sudah: int, terpetakan: int, mandiri: int, ambigu: int, tak_terpeta: int}  $stats */
    private function remaining(array $stats): int
    {
        return $stats['total'] - $stats['sudah'] - $stats['terpetakan'] - $stats['mandiri'] - $stats['ambigu'];
    }
}
