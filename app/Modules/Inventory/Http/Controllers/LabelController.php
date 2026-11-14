<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Services\LabelService;
use App\Modules\Purchasing\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * Label & barcode (F4.4). Halaman pemilih + dokumen cetak label
 * (tab baru, pola dokumen cetak rumah). Sepagar stock.view di rute.
 * A1: pemilih berisi DETAIL produk; nilai pilihan "alias:id".
 */
class LabelController extends Controller
{
    public function __construct(private readonly LabelService $labels) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $details = collect();
        foreach (Product::DETAIL_MODELS as $model) {
            $found = $model::with('product')
                ->where('is_active', true)
                ->when($q !== '', fn ($query) => $query->where(fn ($sub) => $sub
                    ->where('sku', 'like', "%{$q}%")
                    ->orWhere('barcode', 'like', "%{$q}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$q}%"))))
                ->get();
            $details = $details->merge($found);
        }
        $details = $details->sortBy(fn ($d) => [$d->product?->name ?? '', $d->sku])->values();

        $page = max(1, (int) $request->query('page', 1));
        $products = new LengthAwarePaginator(
            $details->forPage($page, 25),
            $details->count(),
            25,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
        $receipts = Receipt::withoutGlobalScopes()
            ->with('purchaseOrder')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('inventory.labels.index', compact('products', 'receipts', 'q'));
    }

    public function print(Request $request): View
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:products,receipt'],
            'selected' => ['nullable', 'array'],
            'selected.*' => ['string', 'regex:/^(frame|lens|rx|softlens|accessory):\d+$/'],
            'copies' => ['nullable', 'array'],
            'copies.*' => ['nullable', 'integer', 'min:0', 'max:500'],
            'receipt_id' => ['nullable', 'integer'],
            'multiplier' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validated['mode'] === 'receipt') {
            $receipt = Receipt::withoutGlobalScopes()->findOrFail((int) ($validated['receipt_id'] ?? 0));
            $labels = $this->labels->forReceipt($receipt, (int) ($validated['multiplier'] ?? 1));
        } else {
            $copies = [];
            foreach ((array) ($validated['selected'] ?? []) as $key) {
                $copies[(string) $key] = (int) (($validated['copies'] ?? [])[$key] ?? 1);
            }
            $labels = $this->labels->forProducts($copies);
        }

        return view('inventory.labels.print', compact('labels'));
    }
}
