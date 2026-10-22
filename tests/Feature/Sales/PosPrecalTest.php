<?php

namespace Tests\Feature\Sales;

use App\Modules\Catalog\Models\Product;
use App\Modules\Clinical\Models\Prescription;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\RxPrecalValidator;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * ADR-019: terminal POS satu halaman + precal manual 11 kunci per item
 * RX (paritas Artesis). Precal terikat frame, divalidasi
 * RxPrecalValidator, tersimpan di sale_items.rx_parameters dan
 * tersalin ke item PR otomatis. Item RX mewajibkan resep pelanggan
 * yang sama. PDR/PDL tidak pernah menjadi kunci precal.
 */
class PosPrecalTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $kasir;

    private Branch $branch;

    private Customer $customer;

    private Product $lensRx;

    private Prescription $prescription;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();

        $this->kasir = User::factory()->create();
        $this->kasir->roles()->sync([Role::where('name', 'kasir')->firstOrFail()->id]);
        $this->kasir->branches()->sync([$this->branch->id]);

        $this->customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $this->lensRx = Product::factory()->ofType('rx')->create(['sale_price' => '300000', 'name' => 'Lensa RX Uji']);
        $this->prescription = Prescription::factory()->create(['customer_id' => $this->customer->id]);
    }

    private function fullPrecal(): array
    {
        return [
            'pvr' => '52', 'pvl' => '50', 'a' => '52', 'b' => '38', 'dbl' => '18',
            'ed' => '54', 'vd' => '12', 'pa' => '8', 'wrap' => '5', 'de' => 'r',
            'type_frame' => 'Full Frame Metal',
        ];
    }

    private function rxSaleData(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'prescription_id' => $this->prescription->id,
            'sale_date' => '2026-10-22',
            'tax_mode' => 'inclusive',
        ], $overrides);
    }

    // ---------- Validator ----------

    public function test_validator_keys_never_include_pd(): void
    {
        foreach (RxPrecalValidator::KEYS as $key) {
            $this->assertStringNotContainsString('pd', $key, "Kunci precal tidak boleh memuat PD: {$key}");
        }
        $this->assertSame(
            ['pvr', 'pvl', 'a', 'b', 'dbl', 'ed', 'vd', 'pa', 'wrap', 'de', 'type_frame'],
            RxPrecalValidator::KEYS,
        );
    }

    public function test_validator_normalize_and_completeness(): void
    {
        $validator = app(RxPrecalValidator::class);

        $empty = $validator->normalize(null);
        $this->assertFalse($empty['complete']);
        $this->assertSame(11, count($empty['precal']));
        $this->assertNull($empty['precal']['pvr']);

        $full = $validator->normalize($this->fullPrecal());
        $this->assertTrue($full['complete']);
        $this->assertSame('R', $full['precal']['de'], 'DE dinormalisasi huruf besar');

        $partial = $validator->normalize(['pvr' => '52']);
        $this->assertFalse($partial['complete']);
    }

    public function test_validator_rejects_non_numeric_de_and_long_type_frame(): void
    {
        $validator = app(RxPrecalValidator::class);

        $errors = $validator->validate(['pvr' => 'abc', 'de' => 'X', 'type_frame' => str_repeat('a', 101)]);
        $this->assertArrayHasKey('pvr', $errors);
        $this->assertArrayHasKey('de', $errors);
        $this->assertArrayHasKey('type_frame', $errors);
        $this->assertSame([], $validator->validate($this->fullPrecal()));
        $this->assertSame([], $validator->validate(null));
    }

    // ---------- Penjualan ----------

    public function test_rx_sale_requires_prescription(): void
    {
        try {
            app(SalesService::class)->createSale($this->kasir, [
                'customer_id' => $this->customer->id,
                'sale_date' => '2026-10-22',
                'tax_mode' => 'inclusive',
            ], [
                ['product_id' => $this->lensRx->id, 'qty' => 1, 'prescription_ref' => 'pair'],
            ]);
            $this->fail('Penjualan RX tanpa resep harus ditolak');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('prescription_id', $e->errors());
        }
        $this->assertSame(0, Sale::count());
    }

    public function test_rx_sale_stores_manual_precal_and_snapshot_without_pd_keys(): void
    {
        $sale = app(SalesService::class)->createSale($this->kasir, $this->rxSaleData(), [
            ['product_id' => $this->lensRx->id, 'qty' => 1, 'prescription_ref' => 'pair', 'precal' => $this->fullPrecal()],
        ]);

        $item = $sale->items()->firstOrFail();
        $params = $item->rx_parameters;
        $this->assertNotNull($params);
        $this->assertTrue($params['precal_complete']);
        $this->assertSame('52', $params['precal']['pvr']);
        $this->assertSame('R', $params['precal']['de']);
        $this->assertSame($this->prescription->id, $params['prescription_id']);
        $this->assertSame($this->prescription->id, $params['prescription']['prescription_id']);

        // Aturan mutlak: tidak ada kunci PDR/PDL di payload tersimpan.
        $keys = [];
        array_walk_recursive($params, function ($value, $key) use (&$keys) {
            $keys[] = strtolower((string) $key);
        });
        foreach ($keys as $key) {
            $this->assertStringNotContainsString('pdr', $key);
            $this->assertStringNotContainsString('pdl', $key);
        }
    }

    public function test_rx_sale_partial_precal_is_flagged_incomplete(): void
    {
        $sale = app(SalesService::class)->createSale($this->kasir, $this->rxSaleData(), [
            ['product_id' => $this->lensRx->id, 'qty' => 1, 'prescription_ref' => 'od', 'precal' => ['pvr' => '52']],
        ]);

        $params = $sale->items()->firstOrFail()->rx_parameters;
        $this->assertFalse($params['precal_complete']);
        $this->assertSame('52', $params['precal']['pvr']);
        $this->assertNull($params['precal']['type_frame']);
    }

    public function test_rx_sale_rejects_invalid_precal_with_item_keyed_errors(): void
    {
        try {
            app(SalesService::class)->createSale($this->kasir, $this->rxSaleData(), [
                ['product_id' => $this->lensRx->id, 'qty' => 1, 'prescription_ref' => 'pair', 'precal' => ['de' => 'X']],
            ]);
            $this->fail('DE tidak valid harus ditolak');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.precal.de', $e->errors());
        }
    }

    public function test_precal_is_copied_to_automatic_pr_items(): void
    {
        $sale = app(SalesService::class)->createSale($this->kasir, $this->rxSaleData(), [
            ['product_id' => $this->lensRx->id, 'qty' => 2, 'prescription_ref' => 'pair', 'precal' => $this->fullPrecal()],
        ]);

        $pr = PurchaseRequest::where('sale_id', $sale->id)->firstOrFail();
        $prItem = $pr->items()->firstOrFail();
        $this->assertNotNull($prItem->rx_parameters);
        $this->assertTrue($prItem->rx_parameters['precal_complete']);
        $this->assertSame('Full Frame Metal', $prItem->rx_parameters['precal']['type_frame']);
    }

    // ---------- Pelengkapan precal sesudah penjualan ----------

    public function test_update_precal_completes_and_syncs_to_pr(): void
    {
        $sale = app(SalesService::class)->createSale($this->kasir, $this->rxSaleData(), [
            ['product_id' => $this->lensRx->id, 'qty' => 1, 'prescription_ref' => 'pair'],
        ]);
        $item = $sale->items()->firstOrFail();
        $this->assertFalse($item->rx_parameters['precal_complete']);

        $this->actingAs($this->owner)
            ->post(route('sales.precal.update', [$sale, $item]), ['precal' => $this->fullPrecal()])
            ->assertRedirect(route('sales.show', $sale));

        $this->assertTrue($item->refresh()->rx_parameters['precal_complete']);
        $prItem = PurchaseRequest::where('sale_id', $sale->id)->firstOrFail()->items()->firstOrFail();
        $this->assertTrue($prItem->rx_parameters['precal_complete'], 'Precal PR ikut tersinkron');
    }

    public function test_update_precal_requires_sales_update_permission(): void
    {
        $sale = app(SalesService::class)->createSale($this->kasir, $this->rxSaleData(), [
            ['product_id' => $this->lensRx->id, 'qty' => 1, 'prescription_ref' => 'pair'],
        ]);
        $item = $sale->items()->firstOrFail();

        $stranger = User::factory()->create();
        $stranger->branches()->sync([$this->branch->id]);
        $this->actingAs($stranger)
            ->post(route('sales.precal.update', [$sale, $item]), ['precal' => $this->fullPrecal()])
            ->assertForbidden();
    }

    // ---------- Terminal & endpoint async ----------

    public function test_terminal_page_and_async_endpoints(): void
    {
        $this->actingAs($this->kasir)
            ->get(route('sales.create'))
            ->assertOk()
            ->assertSee('posTerminal', false)
            ->assertSee('cari-produk', false);

        $this->actingAs($this->kasir)
            ->getJson(route('sales.search-products', ['q' => 'Lensa RX']))
            ->assertOk()
            ->assertJsonFragment(['id' => $this->lensRx->id, 'type' => 'rx']);

        $this->actingAs($this->kasir)
            ->getJson(route('sales.search-customers', ['q' => $this->customer->name]))
            ->assertOk()
            ->assertJsonFragment(['id' => $this->customer->id]);

        $this->actingAs($this->kasir)
            ->getJson(route('sales.customer-prescriptions', $this->customer))
            ->assertOk()
            ->assertJsonFragment(['id' => $this->prescription->id]);

        $stranger = User::factory()->create();
        $this->actingAs($stranger)
            ->getJson(route('sales.search-products'))
            ->assertForbidden();
    }

    public function test_http_store_accepts_terminal_payload_with_precal(): void
    {
        $this->actingAs($this->kasir)->post(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'prescription_id' => $this->prescription->id,
            'sale_date' => '2026-10-22',
            'tax_mode' => 'inclusive',
            'items' => [
                ['product_id' => $this->lensRx->id, 'qty' => 1, 'prescription_ref' => 'pair', 'precal' => $this->fullPrecal()],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => '300000'],
            ],
        ])->assertRedirect();

        $sale = Sale::latest('id')->firstOrFail();
        $this->assertTrue($sale->items()->firstOrFail()->rx_parameters['precal_complete']);
    }

    // ---------- Precal resep otomatis telah dicabut ----------

    public function test_prescription_precal_pages_are_gone(): void
    {
        $this->actingAs($this->owner)->get('/resep/'.$this->prescription->id.'/precal')->assertNotFound();
        $this->actingAs($this->owner)->get('/resep/'.$this->prescription->id.'/precal/cetak')->assertNotFound();
        $this->assertFalse(class_exists(\App\Modules\Clinical\Services\PrecalService::class));
    }
}
