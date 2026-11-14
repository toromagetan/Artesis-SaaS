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
            'intents.*.items.*.detail_type' => ['required', 'string', 'in:frame,lens,rx,softlens,accessory'],
            'intents.*.items.*.detail_id' => ['required', 'integer'],
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
        // A1: snapshot berisi DETAIL aktif (unit jual); kunci klien
        // adalah "alias:id" karena id hanya unik per tabel detail.
        $products = collect();
        foreach (Product::DETAIL_MODELS as $model) {
            $found = $model::with('product')
                ->where('is_active', true)
                ->whereHas('product', fn ($p) => $p->where('is_active', true))
                ->get()
                ->map(fn ($d) => [
                    'key' => $d::DETAIL_TYPE.':'.$d->id,
                    'detail_type' => $d::DETAIL_TYPE,
                    'detail_id' => $d->id,
                    'sku' => $d->sku,
                    'name' => $d->product?->name,
                    'detail_label' => $d->detailLabel(),
                    'type' => $d::DETAIL_TYPE,
                    'sale_price' => (string) $d->sale_price,
                    'stock' => $balances[$d::DETAIL_TYPE.':'.$d->id] ?? 0,
                ]);
            $products = $products->concat($found);
        }
        $products = $products->sortBy(fn ($p) => [$p['name'] ?? '', $p['sku']])->values();
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
