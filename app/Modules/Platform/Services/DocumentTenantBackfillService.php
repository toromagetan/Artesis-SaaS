<?php

namespace App\Modules\Platform\Services;

use Illuminate\Support\Facades\DB;

/**
 * Isi ulang tenant_id dokumen bisnis (Keputusan C PR-1,
 * keputusan owner 2026-11-18): memetakan setiap dokumen lama ke
 * tenant pemiliknya HANYA dari sumber kepemilikan terverifikasi,
 * dan tenant_id yang dicap HARUS sama dengan ember yang dipakai
 * Numbering saat nomor dokumen itu dicetak (rancangan teknis
 * §2 + koreksi audit antara C-01/C-02):
 *
 * - sales, sale_returns, purchase_requests, purchase_orders,
 *   warranty_cases, customer_credits: cabang dokumen (ember
 *   nextForBranch).
 * - receipts: PO -> cabang PO. purchase_returns: penerimaan ->
 *   PO -> cabang PO.
 * - purchase_payments: sumber UTAMA adalah pemasok
 *   (suppliers.tenant_id) — itulah ember yang dipakai
 *   Numbering::next('PAY', ..., $supplier->tenant_id, true)
 *   oleh PurchasePaymentService dan CollectivePaymentService.
 *   Alokasi (penerimaan -> PO -> cabang) hanya MEMVALIDASI:
 *   bila ada alokasi yang menunjuk tenant berbeda dari tenant
 *   pemasok, baris itu AMBIGU — dilaporkan dan DIBIARKAN NULL.
 *   Pembayaran tanpa alokasi tetap terpetakan dari pemasoknya.
 *   Pemasok bertenant NULL = ember NULL yang memang dipilih
 *   Numbering, jadi MANDIRI selama alokasi tidak membantahnya.
 * - stock_transfers: cabang asal & tujuan harus membuktikan
 *   tenant yang sama; berbeda = AMBIGU; melibatkan cabang
 *   bertenant NULL pada instalasi bertenant = TAK TERPETA.
 * - journals: hanya diverifikasi (sudah dicap sejak 000055).
 *
 * Kebijakan ember NULL (selaras Numbering::nextForBranch):
 * cabang bertenant NULL hanya sah sebagai sumber ember mandiri
 * bila instalasi BELUM memiliki tenant sama sekali. Pada
 * instalasi bertenant, dokumen dari cabang bertenant NULL
 * adalah TAK TERPETA (temuan), bukan mandiri — dan berlaku
 * sama untuk jurnal bertenant NULL.
 *
 * Idempoten: hanya baris ber-tenant_id NULL yang diproses.
 */
class DocumentTenantBackfillService
{
    /**
     * @return array<string, array{total: int, sudah: int, terpetakan: int, mandiri: int, ambigu: int, tak_terpeta: int}>
     */
    public function run(bool $verify = false): array
    {
        $hasTenants = DB::table('tenants')->count() > 0;

        $report = [];
        foreach (['sales', 'sale_returns', 'purchase_requests', 'purchase_orders', 'warranty_cases', 'customer_credits'] as $table) {
            $report[$table] = $this->viaBranch($table, 'branch_id', $verify, $hasTenants);
        }
        $report['receipts'] = $this->viaPurchaseOrderBranch('receipts', 'purchase_order_id', $verify, $hasTenants);
        $report['purchase_returns'] = $this->viaReceiptPurchaseOrderBranch($verify, $hasTenants);
        $report['purchase_payments'] = $this->payments($verify);
        $report['stock_transfers'] = $this->transfers($verify, $hasTenants);
        $report['journals'] = $this->journalsOnly($hasTenants);

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
    private function viaBranch(string $table, string $branchColumn, bool $verify, bool $hasTenants): array
    {
        $stats = $this->baseStats($table);

        $resolvable = DB::table("{$table} as d")
            ->join('branches as b', "d.{$branchColumn}", '=', 'b.id')
            ->whereNull('d.tenant_id')->whereNotNull('b.tenant_id');
        $stats['terpetakan'] = (clone $resolvable)->count();
        if (! $verify && $stats['terpetakan'] > 0) {
            (clone $resolvable)->update(['d.tenant_id' => DB::raw('b.tenant_id')]);
        }

        // Cabang bertenant NULL hanya ember mandiri yang sah bila
        // instalasi belum memiliki tenant (kebijakan
        // Numbering::nextForBranch). Pada instalasi bertenant
        // baris-baris itu jatuh ke tak_terpeta lewat remaining().
        $stats['mandiri'] = $hasTenants ? 0 : DB::table("{$table} as d")
            ->join('branches as b', "d.{$branchColumn}", '=', 'b.id')
            ->whereNull('d.tenant_id')->whereNull('b.tenant_id')->count();
        $stats['tak_terpeta'] = $this->remaining($stats);

        return $stats;
    }

    /** @return array{total: int, sudah: int, terpetakan: int, mandiri: int, ambigu: int, tak_terpeta: int} */
    private function viaPurchaseOrderBranch(string $table, string $poColumn, bool $verify, bool $hasTenants): array
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

        $stats['mandiri'] = $hasTenants ? 0 : DB::table("{$table} as d")
            ->join('purchase_orders as po', "d.{$poColumn}", '=', 'po.id')
            ->join('branches as b', 'po.branch_id', '=', 'b.id')
            ->whereNull('d.tenant_id')->whereNull('b.tenant_id')->count();
        $stats['tak_terpeta'] = $this->remaining($stats);

        return $stats;
    }

    /** @return array{total: int, sudah: int, terpetakan: int, mandiri: int, ambigu: int, tak_terpeta: int} */
    private function viaReceiptPurchaseOrderBranch(bool $verify, bool $hasTenants): array
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

        $stats['mandiri'] = $hasTenants ? 0 : DB::table('purchase_returns as d')
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

        $pending = DB::table('purchase_payments as p')
            ->leftJoin('suppliers as s', 'p.supplier_id', '=', 's.id')
            ->whereNull('p.tenant_id')
            ->select('p.id', 's.tenant_id as supplier_tenant')
            ->get();
        foreach ($pending as $payment) {
            $supplierTenant = $payment->supplier_tenant !== null ? (int) $payment->supplier_tenant : null;

            // Validasi silang: tenant yang dibuktikan alokasi
            // (penerimaan -> PO -> cabang). NULL pada rantai
            // alokasi bukan bukti tenant mana pun.
            $allocationTenants = DB::table('purchase_payment_allocations as a')
                ->join('receipts as r', 'a.receipt_id', '=', 'r.id')
                ->join('purchase_orders as po', 'r.purchase_order_id', '=', 'po.id')
                ->join('branches as b', 'po.branch_id', '=', 'b.id')
                ->where('a.purchase_payment_id', $payment->id)
                ->whereNotNull('b.tenant_id')
                ->distinct()->pluck('b.tenant_id')
                ->map(fn ($t) => (int) $t)->all();

            $contradicts = false;
            foreach ($allocationTenants as $tenant) {
                if ($tenant !== $supplierTenant) {
                    $contradicts = true;
                    break;
                }
            }
            if ($contradicts) {
                // Alokasi membantah ember pemasok: kepemilikan
                // tidak dapat dibuktikan — laporkan, jangan cap.
                $stats['ambigu']++;

                continue;
            }
            if ($supplierTenant !== null) {
                $stats['terpetakan']++;
                if (! $verify) {
                    DB::table('purchase_payments')->where('id', $payment->id)
                        ->update(['tenant_id' => $supplierTenant]);
                }
            } else {
                // Ember NULL memang yang dipilih Numbering untuk
                // pemasok ini dan alokasi tidak membantahnya.
                $stats['mandiri']++;
            }
        }

        return $stats;
    }

    /** @return array{total: int, sudah: int, terpetakan: int, mandiri: int, ambigu: int, tak_terpeta: int} */
    private function transfers(bool $verify, bool $hasTenants): array
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

        // Kedua cabang bertenant NULL: mandiri hanya pada
        // instalasi tanpa tenant; selain itu tak_terpeta.
        $stats['mandiri'] = $hasTenants ? 0 : DB::table('stock_transfers as d')
            ->join('branches as bf', 'd.from_branch_id', '=', 'bf.id')
            ->join('branches as bt', 'd.to_branch_id', '=', 'bt.id')
            ->whereNull('d.tenant_id')->whereNull('bf.tenant_id')->whereNull('bt.tenant_id')->count();
        // Kedua cabang bertenant tetapi berbeda: ambigu.
        $stats['ambigu'] = DB::table('stock_transfers as d')
            ->join('branches as bf', 'd.from_branch_id', '=', 'bf.id')
            ->join('branches as bt', 'd.to_branch_id', '=', 'bt.id')
            ->whereNull('d.tenant_id')->whereNotNull('bf.tenant_id')->whereNotNull('bt.tenant_id')
            ->whereColumn('bf.tenant_id', '<>', 'bt.tenant_id')->count();
        // Sisa (melibatkan cabang NULL pada instalasi bertenant,
        // atau satu sisi NULL): tak_terpeta.
        $stats['tak_terpeta'] = $this->remaining($stats);

        return $stats;
    }

    /** @return array{total: int, sudah: int, terpetakan: int, mandiri: int, ambigu: int, tak_terpeta: int} */
    private function journalsOnly(bool $hasTenants): array
    {
        $stats = $this->baseStats('journals');
        // journals tidak diisi ulang. NULL di sini adalah ember
        // mandiri hanya bila instalasi belum memiliki tenant;
        // pada instalasi bertenant ia temuan yang harus
        // didisposisi audit antara, bukan dianggap bersih.
        if ($hasTenants) {
            $stats['tak_terpeta'] = $stats['total'] - $stats['sudah'];
        } else {
            $stats['mandiri'] = $stats['total'] - $stats['sudah'];
        }

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
