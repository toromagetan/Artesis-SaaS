<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\FrameDetail;
use App\Modules\Catalog\Models\LensDetail;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\SoftlensDetail;
use App\Modules\Catalog\Services\ProductExcelImportService;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Support\XlsxWriter;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A4 — impor produk Excel/CSV persis Artesis, dipagari SaaS:
 * validasi penuh sebelum transaksi, atomik, SKU unik per tenant,
 * stok tidak berubah, pembuatan lewat ProductService.
 */
class ProductExcelImportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private ProductExcelImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->service = app(ProductExcelImportService::class);
    }

    private function csvFile(string $content, string $name = 'impor.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_imports_mixed_types_and_groups_masters(): void
    {
        $csv = "type,brand,name,sku,purchase_price,selling_price,color,lens_type,index,eye_side,sph,cyl,power,expiry_tracked\n"
            ."frame,RayBan,Aviator,FRM-IMP-1,450000,750000,Gold,,,,,,\n"
            ."frame,RayBan,Aviator,FRM-IMP-2,460000,760000,Silver,,,,,,\n"
            ."lens,Essilor,FSV Bluecut,LNS-IMP-1,120000,250000,,SV,1.56,Both,-1.00,-0.50,,\n"
            ."softlens,X2,X2 Bio,SFT-IMP-1,45000,85000,Brown,,,,,,-2.00,1\n";

        $result = $this->service->import($this->owner, $this->csvFile($csv));

        $this->assertSame(4, $result['created']);
        $this->assertFalse($result['stock_changed']);
        // Dua baris Aviator berbagi SATU master.
        $this->assertSame(1, Product::where('name', 'Aviator')->count());
        $this->assertSame(2, FrameDetail::whereIn('sku', ['FRM-IMP-1', 'FRM-IMP-2'])->count());
        $lens = LensDetail::where('sku', 'LNS-IMP-1')->firstOrFail();
        $this->assertSame('SV', $lens->type);
        $this->assertSame('1.56', $lens->index);
        $this->assertEqualsWithDelta(-1.00, (float) $lens->sph, 0.001);
        $soft = SoftlensDetail::where('sku', 'SFT-IMP-1')->firstOrFail();
        $this->assertTrue((bool) $soft->expiry_tracked);
        // Stok tidak disentuh impor produk.
        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_xlsx_roundtrip_through_the_reader(): void
    {
        $bytes = XlsxWriter::make([['title' => 'Template Import', 'rows' => [
            ['type', 'name', 'sku', 'purchase_price', 'selling_price'],
            ['frame', 'Frame XLSX', 'FRM-XLSX-1', 1000, 2000],
        ]]]);
        $file = UploadedFile::fake()->createWithContent('impor.xlsx', $bytes);

        $result = $this->service->import($this->owner, $file);
        $this->assertSame(1, $result['created']);
        $this->assertNotNull(FrameDetail::where('sku', 'FRM-XLSX-1')->first());
    }

    public function test_duplicate_sku_in_file_rejects_everything(): void
    {
        $csv = "type,name,sku,purchase_price,selling_price\n"
            ."frame,Satu,FRM-DUP,100,200\n"
            ."frame,Dua,FRM-DUP,100,200\n";

        try {
            $this->service->import($this->owner, $this->csvFile($csv));
            $this->fail('Impor seharusnya gagal.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors()['file'] ?? []);
        }
        $this->assertSame(0, FrameDetail::count());
        $this->assertSame(0, Product::whereIn('name', ['Satu', 'Dua'])->count());
    }

    public function test_existing_tenant_sku_rejected_but_other_tenant_sku_allowed(): void
    {
        FrameDetail::factory()->create(['sku' => 'FRM-MILIK-OWNER']);
        $csv = "type,name,sku,purchase_price,selling_price\nframe,Baru,FRM-MILIK-OWNER,100,200\n";
        $this->expectException(ValidationException::class);
        try {
            $this->service->import($this->owner, $this->csvFile($csv));
        } finally {
            $this->assertSame(1, FrameDetail::where('sku', 'FRM-MILIK-OWNER')->count());
        }
    }

    public function test_same_sku_in_another_tenant_does_not_block(): void
    {
        // Dua tenant nyata dengan pemilik masing-masing: SKU hanya
        // unik PER TENANT (§192) — milik tenant lain tidak menghalangi.
        $makeOwner = function (string $slug): User {
            $tenantId = DB::table('tenants')->insertGetId([
                'name' => 'Tenant '.$slug, 'slug' => $slug, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $user = User::create([
                'name' => 'Owner '.$slug, 'username' => 'owner-'.$slug,
                'password' => bcrypt('password'), 'is_active' => true, 'tenant_id' => $tenantId,
            ]);
            $user->roles()->attach(Role::where('name', 'Owner')->firstOrFail());

            return $user;
        };
        $ownerA = $makeOwner('tenant-a');
        $ownerB = $makeOwner('tenant-b');

        $masterB = Product::factory()->forType('frame')->create(['tenant_id' => $ownerB->tenant_id]);
        FrameDetail::factory()->create(['product_id' => $masterB->id, 'tenant_id' => $ownerB->tenant_id, 'sku' => 'FRM-LINTAS']);

        $csv = "type,name,sku,purchase_price,selling_price\nframe,Milik A,FRM-LINTAS,100,200\n";
        $result = $this->service->import($ownerA, $this->csvFile($csv));
        $this->assertSame(1, $result['created']);
        $this->assertSame(1, FrameDetail::where('sku', 'FRM-LINTAS')->where('tenant_id', $ownerA->tenant_id)->count());
    }

    public function test_invalid_rows_reject_the_whole_file_atomically(): void
    {
        $csv = "type,name,sku,purchase_price,selling_price,lens_type,sph\n"
            ."frame,Bagus,FRM-OK,100,200,,\n"
            ."lens,Rusak,LNS-BAD,100,200,SV,-1.13\n";

        $this->expectException(ValidationException::class);
        try {
            $this->service->import($this->owner, $this->csvFile($csv));
        } finally {
            $this->assertSame(0, FrameDetail::count());
            $this->assertSame(0, LensDetail::count());
        }
    }

    public function test_http_form_template_and_permission_fence(): void
    {
        $this->actingAs($this->owner)->get('/katalog/impor')->assertOk()->assertSee('Impor Produk');
        $this->actingAs($this->owner)->get('/katalog/impor/template')->assertOk();

        $kasir = User::create(['name' => 'Kasir', 'username' => 'kasir-impor', 'password' => bcrypt('password'), 'is_active' => true]);
        $kasir->roles()->attach(Role::where('name', 'Kasir')->firstOrFail());
        $this->actingAs($kasir)->get('/katalog/impor')->assertForbidden();
        $this->actingAs($kasir)->post('/katalog/impor', ['file' => $this->csvFile("type,name,sku,purchase_price,selling_price\nframe,X,FRM-K,1,2\n")])->assertForbidden();
        $this->assertSame(0, FrameDetail::where('sku', 'FRM-K')->count());
    }

    public function test_http_import_redirects_with_success_message(): void
    {
        $csv = "type,name,sku,purchase_price,selling_price\nframe,HTTP Frame,FRM-HTTP,100,200\n";
        $this->actingAs($this->owner)->post('/katalog/impor', ['file' => $this->csvFile($csv)])
            ->assertRedirect(route('products.import.form'));
        $this->assertNotNull(FrameDetail::where('sku', 'FRM-HTTP')->first());
    }
}
