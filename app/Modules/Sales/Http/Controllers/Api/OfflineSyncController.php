<?php

namespace App\Modules\Sales\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Sales\Models\OfflineDevice;
use App\Modules\Sales\Services\OfflineSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint sinkronisasi kasir offline (F5.3): perangkat mengirim
 * antrean niat transaksi; server membalas hasil per niat
 * (synced / duplicate / exception). Seluruh pembuatan penjualan
 * lewat SalesService — tidak ada jalur stok kedua.
 */
class OfflineSyncController extends Controller
{
    public function sync(Request $request, OfflineSyncService $sync): JsonResponse
    {
        $validated = $request->validate([
            'intents' => ['required', 'array', 'min:1', 'max:200'],
            'intents.*.client_uuid' => ['required', 'string', 'max:64'],
            'intents.*.local_no' => ['nullable', 'string', 'max:50'],
            'intents.*.offline_at' => ['required', 'date'],
            'intents.*.customer_id' => ['required', 'integer'],
            'intents.*.tax_mode' => ['nullable', 'in:exclusive,inclusive,separate'],
            'intents.*.items' => ['required', 'array', 'min:1'],
            'intents.*.items.*.product_id' => ['required', 'integer'],
            'intents.*.items.*.qty' => ['required', 'integer', 'min:1'],
            'intents.*.items.*.unit_price' => ['nullable'],
            'intents.*.items.*.discount' => ['nullable'],
            'intents.*.payments' => ['nullable', 'array'],
            'intents.*.payments.*.method' => ['required', 'string'],
            'intents.*.payments.*.amount' => ['required'],
        ]);

        /** @var OfflineDevice $device */
        $device = $request->attributes->get('offline_device');
        $results = $sync->sync($device, $validated['intents']);

        return response()->json(['results' => $results]);
    }

    /**
     * Snapshot katalog + stok cabang untuk klien offline (F5.3):
     * produk aktif, harga, dan saldo cabang perangkat saat ini.
     */
    public function snapshot(Request $request): JsonResponse
    {
        /** @var OfflineDevice $device */
        $device = $request->attributes->get('offline_device');
        $balances = app(InventoryService::class)->balancesForBranch($device->branch_id);
        $products = Product::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'type', 'sale_price'])
            ->map(fn ($p) => [
                'id' => $p->id, 'sku' => $p->sku, 'name' => $p->name, 'type' => $p->type,
                'sale_price' => (string) $p->sale_price, 'stock' => $balances[$p->id] ?? 0,
            ]);
        $customers = Customer::orderBy('name')
            ->limit(2000)->get(['id', 'name', 'phone'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone]);

        return response()->json([
            'branch_id' => $device->branch_id,
            'taken_at' => now()->toIso8601String(),
            'products' => $products,
            'customers' => $customers,
        ]);
    }
}
