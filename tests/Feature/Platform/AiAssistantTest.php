<?php

namespace Tests\Feature\Platform;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Models\AiSetting;
use App\Modules\Platform\Services\AiAssistantService;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * F5.1 Asisten AI — pengaturan terenkripsi via UI, penerjemah
 * intent (HTTP difake), eksekusi read-only dari service dengan
 * angka persis ground truth, penolakan intent asing, dan gerbang
 * owner-only.
 */
class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        Setting::set('tax.rate_default', '0');
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
    }

    private function configureAi(): void
    {
        AiSetting::current()->update([
            'base_url' => 'https://ai.example.test/v1',
            'model' => 'model-uji',
            'credentials_encrypted' => ['api_key' => 'sk-rahasia-1234'],
            'is_active' => true,
        ]);
    }

    private function fakeTranslator(): void
    {
        Http::fake([
            'ai.example.test/*' => function ($request) {
                $question = (string) $request->data()['messages'][1]['content'];
                $payload = match (true) {
                    str_contains($question, 'omzet') => ['intent' => 'sales_total', 'params' => ['period' => 'current_month']],
                    str_contains($question, 'Stok') => ['intent' => 'stock_qty', 'params' => ['product' => 'Frame Uji']],
                    default => ['intent' => 'delete_sale', 'params' => []],
                };

                return Http::response(['choices' => [['message' => ['content' => json_encode($payload)]]]], 200);
            },
        ]);
    }

    private function fixtureSale(): Product
    {
        $supplier = Supplier::create(['name' => 'PT AI', 'is_active' => true]);
        $product = Product::factory()->ofType('frame')->create(['sku' => 'AI-1', 'name' => 'Frame Uji', 'cost_price' => '50000', 'sale_price' => '100000']);
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($this->owner, $supplier->id, $this->branch->id, [
            ['product_id' => $product->id, 'qty' => 5, 'unit_cost' => '50000'],
        ]);
        $orders->markSent($this->owner, $po);
        app(ReceiptService::class)->store($this->owner, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 5, 'unit_cost_actual' => '50000'],
        ]);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $customer->id, 'sale_date' => now()->toDateString(), 'tax_mode' => 'exclusive', 'branch_id' => $this->branch->id,
        ], [['product_id' => $product->id, 'qty' => 2]], [['method' => 'cash', 'amount' => '200000']]);

        return $product;
    }

    public function test_settings_save_encrypts_api_key_and_keeps_it_when_blank(): void
    {
        $this->actingAs($this->owner)->put(route('ai-setting.update'), [
            'base_url' => 'https://ai.example.test/v1', 'model' => 'model-uji',
            'api_key' => 'sk-rahasia-1234', 'is_active' => 1,
        ])->assertRedirect(route('ai-setting.edit'));

        $raw = DB::table('ai_settings')->value('credentials_encrypted');
        $this->assertNotSame('sk-rahasia-1234', $raw);
        $this->assertStringNotContainsString('sk-rahasia-1234', (string) $raw);
        $this->assertSame('sk-rahasia-1234', AiSetting::current()->apiKey());
        $this->assertSame('••••1234', AiSetting::current()->maskedKey());

        // Simpan ulang tanpa key -> key lama dipertahankan.
        $this->actingAs($this->owner)->put(route('ai-setting.update'), [
            'base_url' => 'https://ai.example.test/v1', 'model' => 'model-lain', 'api_key' => '', 'is_active' => 1,
        ])->assertRedirect(route('ai-setting.edit'));
        $this->assertSame('sk-rahasia-1234', AiSetting::current()->apiKey());
        $this->assertSame('model-lain', AiSetting::current()->model);
    }

    public function test_assistant_answers_from_services_with_basis_and_refuses_foreign_intents(): void
    {
        $this->fixtureSale();
        $this->configureAi();
        $assistant = app(AiAssistantService::class);

        $this->fakeTranslator();
        $result = $assistant->ask($this->owner, 'Berapa omzet bulan ini?');
        $this->assertSame('ok', $result['status']);
        $this->assertSame('Rp200.000,00', $result['text']);
        $this->assertSame([$this->branch->name], $result['basis']['branches']);

        $result = $assistant->ask($this->owner, 'Stok Frame Uji?');
        $this->assertSame('ok', $result['status']);
        $this->assertSame('Frame Uji: 3', $result['text']);

        // Intent asing dari penyedia (di luar daftar putih) -> unknown, tanpa eksekusi.
        $this->assertSame('unknown', $assistant->ask($this->owner, 'Hapus penjualan')['status']);
    }

    public function test_assistant_not_configured_and_owner_only_gate(): void
    {
        $assistant = app(AiAssistantService::class);
        $this->assertSame('ai_not_configured', $assistant->ask($this->owner, 'Berapa omzet?')['status']);

        $kasir = User::factory()->create();
        $kasir->roles()->sync([Role::where('name', 'Kasir')->firstOrFail()->id]);
        $this->actingAs($kasir)->get(route('assistant.index'))->assertForbidden();
        $this->actingAs($this->owner)->get(route('assistant.index'))->assertOk();
    }
}
