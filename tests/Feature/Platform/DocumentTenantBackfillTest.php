<?php

namespace Tests\Feature\Platform;

use App\Modules\Catalog\Models\FrameDetail;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\DocumentTenantBackfillService;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\PurchasePaymentService;
use App\Modules\Purchasing\Services\PurchaseRequestService;
use App\Modules\Purchasing\Services\ReceiptService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Keputusan C PR-1 — skema + isi ulang: migrasi 000069 memberi
 * tenant_id nullable ber-FK restrict pada 10 tabel dokumen;
 * perintah lentera:backfill-tenant-dokumen memetakan baris lama
 * hanya dari sumber kepemilikan terverifikasi, melaporkan yang
 * tak terpeta/ambigu tanpa menebak, dan idempoten.
 */
class DocumentTenantBackfillTest extends TestCase
{
    use RefreshDatabase;

    private User $standaloneOwner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->standaloneOwner = User::where('username', 'owner')->firstOrFail();
    }

    /** @return array{Tenant, User, Branch} */
    private function tenantFixture(string $tag): array
    {
        $tenant = Tenant::create([
            'name' => 'Optik '.$tag, 'slug' => 'optik-'.$tag, 'email' => $tag.'@uji.id',
        ]);
        $branch = Branch::create(['name' => 'Cabang '.$tag, 'tenant_id' => $tenant->id]);
        $owner = User::create([
            'email_verified_at' => now(),
            'name' => 'Owner '.$tag, 'username' => 'owner-'.$tag,
            'email' => 'owner-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $owner->branches()->attach($branch);

        return [$tenant, $owner, $branch];
    }

    public function test_schema_ten_document_tables_have_restrict_tenant_fk(): void
    {
        $tables = ['sales', 'sale_returns', 'purchase_requests', 'purchase_orders', 'receipts',
            'purchase_payments', 'purchase_returns', 'stock_transfers', 'warranty_cases', 'customer_credits'];
        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'tenant_id'), $table);
            $rule = DB::table('information_schema.REFERENTIAL_CONSTRAINTS as rc')
                ->join('information_schema.KEY_COLUMN_USAGE as kcu', function ($join) {
                    $join->on('rc.CONSTRAINT_NAME', '=', 'kcu.CONSTRAINT_NAME')
                        ->on('rc.CONSTRAINT_SCHEMA', '=', 'kcu.CONSTRAINT_SCHEMA');
                })
                ->where('kcu.TABLE_NAME', $table)
                ->where('kcu.COLUMN_NAME', 'tenant_id')
                ->where('kcu.REFERENCED_TABLE_NAME', 'tenants')
                ->value('rc.DELETE_RULE');
            $this->assertSame('RESTRICT', $rule, $table);
        }
        // journals sudah bertenant sejak 000055 — tidak berubah.
        $this->assertTrue(Schema::hasColumn('journals', 'tenant_id'));
    }

    public function test_backfill_maps_only_from_verified_ownership_and_is_idempotent(): void
    {
        [$tenantA, $ownerA, $branchA] = $this->tenantFixture('a');
        [$tenantB, , $branchB] = $this->tenantFixture('b');
        $branchA2 = Branch::create(['name' => 'Cabang Kedua A', 'tenant_id' => $tenantA->id]);
        $branchS = Branch::create(['name' => 'Mandiri Satu']);
        $branchS2 = Branch::create(['name' => 'Mandiri Dua']);
        $supplier = Supplier::create(['name' => 'PT Uji', 'is_active' => true, 'tenant_id' => $tenantA->id]);
        $customer = Customer::factory()->create(['branch_id' => $branchA->id, 'tenant_id' => $tenantA->id]);
        $frame = FrameDetail::factory()->create([
            'product_id' => Product::factory()->forType('frame')
                ->create(['name' => 'Frame '.Str::random(6), 'tenant_id' => $tenantA->id])->id,
            'sku' => 'BF-'.strtoupper(Str::random(10)),
            'cost_price' => '40000', 'sale_price' => '100000', 'tenant_id' => $tenantA->id,
        ]);

        // --- Rantai pembelian tenant A lewat service, lalu capnya
        // dihapus untuk menyimulasikan baris lama pra-000069. ---
        $pr = app(PurchaseRequestService::class)->create($ownerA, $branchA->id, [
            ['detail_type' => 'frame', 'detail_id' => $frame->id, 'qty' => 2],
        ]);
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($ownerA, $supplier->id, $branchA->id, [
            ['detail_type' => 'frame', 'detail_id' => $frame->id, 'qty' => 2, 'unit_cost' => '100000'],
        ]);
        $orders->markSent($ownerA, $po);
        $receipt = app(ReceiptService::class)->store($ownerA, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 2],
        ], now()->toDateString());
        $payment = app(PurchasePaymentService::class)->paySingle($ownerA, $receipt, '100000', 'cash');
        foreach (['purchase_requests' => $pr->id, 'purchase_orders' => $po->id, 'receipts' => $receipt->id, 'purchase_payments' => $payment->id] as $table => $id) {
            DB::table($table)->where('id', $id)->update(['tenant_id' => null]);
        }

        // --- Baris lama keluarga penjualan (sisip langsung). ---
        $now = now();
        $saleId = DB::table('sales')->insertGetId([
            'number' => 'LEG-INV-A', 'customer_id' => $customer->id, 'branch_id' => $branchA->id,
            'cashier_id' => $ownerA->id, 'sale_date' => '2026-10-01', 'subtotal_amount' => '100000',
            'discount_amount' => '0', 'tax_amount' => '0', 'tax_mode' => 'inclusive',
            'total_amount' => '100000', 'paid_amount' => '100000', 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $saleItemId = DB::table('sale_items')->insertGetId([
            'sale_id' => $saleId, 'qty' => 1, 'unit_price' => '100000', 'line_total' => '100000',
            'product_name_snapshot' => 'Frame Lama', 'type' => 'frame',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('sale_returns')->insert([
            'number' => 'LEG-SR-A', 'sale_id' => $saleId, 'branch_id' => $branchA->id,
            'reason' => 'Uji', 'refund_method' => 'cash', 'total_amount' => '100000',
            'created_by' => $ownerA->id, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('warranty_cases')->insert([
            'number' => 'LEG-WR-A', 'sale_id' => $saleId, 'sale_item_id' => $saleItemId,
            'branch_id' => $branchA->id, 'status' => 'open', 'reason' => 'Cacat', 'created_by' => $ownerA->id,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('customer_credits')->insert([
            'credit_number' => 'LEG-CR-A', 'customer_id' => $customer->id, 'sale_id' => $saleId,
            'branch_id' => $branchA->id, 'amount' => '50000', 'remaining_amount' => '50000',
            'status' => 'active', 'created_by' => $ownerA->id, 'issued_at' => $now,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('purchase_returns')->insert([
            'number' => 'LEG-BR-A', 'receipt_id' => $receipt->id, 'reason' => 'Uji',
            'total_amount' => '100000', 'credit_status' => 'reduces_payable',
            'created_by' => $ownerA->id, 'created_at' => $now, 'updated_at' => $now,
        ]);

        // Penjualan ember mandiri (cabang tanpa tenant).
        DB::table('sales')->insert([
            'number' => 'LEG-INV-S', 'customer_id' => $customer->id, 'branch_id' => $branchS->id,
            'cashier_id' => $this->standaloneOwner->id, 'sale_date' => '2026-10-01',
            'subtotal_amount' => '100000', 'discount_amount' => '0', 'tax_amount' => '0',
            'tax_mode' => 'inclusive', 'total_amount' => '100000', 'paid_amount' => '100000',
            'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ]);

        // Pembayaran yatim: tanpa alokasi sama sekali.
        DB::table('purchase_payments')->insert([
            'number' => 'LEG-PAY-YATIM', 'supplier_id' => $supplier->id, 'method' => 'cash',
            'amount' => '1000', 'payment_date' => '2026-10-01', 'paid_by' => $ownerA->id,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // Transfer: se-tenant (terpetakan), mandiri (sah NULL),
        // lintas tenant (ambigu — temuan, bukan tebakan).
        foreach ([['LEG-TRF-A', $branchA->id, $branchA2->id], ['LEG-TRF-S', $branchS->id, $branchS2->id], ['LEG-TRF-X', $branchA->id, $branchB->id]] as [$number, $from, $to]) {
            DB::table('stock_transfers')->insert([
                'number' => $number, 'from_branch_id' => $from, 'to_branch_id' => $to,
                'status' => 'sent', 'source_type' => 'branch_stock', 'sent_by' => $ownerA->id,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $service = app(DocumentTenantBackfillService::class);

        // --- Mode verify: melaporkan tanpa menulis apa pun. ---
        $verify = $service->run(true);
        $this->assertSame(1, $verify['sales']['terpetakan']);
        $this->assertSame(1, $verify['sales']['mandiri']);
        $this->assertSame(1, $verify['purchase_payments']['terpetakan']);
        $this->assertSame(1, $verify['purchase_payments']['tak_terpeta']);
        $this->assertSame(1, $verify['stock_transfers']['terpetakan']);
        $this->assertSame(1, $verify['stock_transfers']['mandiri']);
        $this->assertSame(1, $verify['stock_transfers']['ambigu']);
        $this->assertTrue(DocumentTenantBackfillService::hasFindings($verify));
        $this->assertNull(DB::table('sales')->where('id', $saleId)->value('tenant_id'));

        // --- Mode tulis: memetakan yang terbukti, membiarkan
        // temuan apa adanya. ---
        $service->run(false);
        $this->assertSame($tenantA->id, (int) DB::table('sales')->where('id', $saleId)->value('tenant_id'));
        $this->assertSame($tenantA->id, (int) DB::table('receipts')->where('id', $receipt->id)->value('tenant_id'));
        $this->assertSame($tenantA->id, (int) DB::table('purchase_payments')->where('id', $payment->id)->value('tenant_id'));
        $this->assertSame($tenantA->id, (int) DB::table('stock_transfers')->where('number', 'LEG-TRF-A')->value('tenant_id'));
        $this->assertNull(DB::table('sales')->where('number', 'LEG-INV-S')->value('tenant_id'));
        $this->assertNull(DB::table('purchase_payments')->where('number', 'LEG-PAY-YATIM')->value('tenant_id'));
        $this->assertNull(DB::table('stock_transfers')->where('number', 'LEG-TRF-X')->value('tenant_id'));

        // --- Idempoten: jalan kedua tidak memetakan apa pun lagi.
        $second = $service->run(false);
        foreach ($second as $table => $row) {
            $this->assertSame(0, $row['terpetakan'], $table);
        }

        // --- Perintah artisan terhubung; --verify menjadi gerbang
        // (keluar 1 selama temuan belum berdisposisi). ---
        $this->assertSame(0, Artisan::call('lentera:backfill-tenant-dokumen'));
        $this->assertSame(1, Artisan::call('lentera:backfill-tenant-dokumen', ['--verify' => true]));
    }
}
