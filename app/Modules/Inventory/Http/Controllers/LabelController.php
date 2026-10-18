<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Services\LabelService;
use App\Modules\Purchasing\Models\Receipt;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Label & barcode (F4.4). Halaman pemilih + dokumen cetak label
 * (tab baru, pola dokumen cetak rumah). Sepagar stock.view di rute.
 */
class LabelController extends Controller
{
    public function __construct(private readonly LabelService $labels) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $products = Product::query()
            ->where('is_active', true)
            ->when($q !== '', fn ($query) => $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('sku', 'like', "%{$q}%")
                ->orWhere('barcode', 'like', "%{$q}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();
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
            'selected.*' => ['integer'],
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
            foreach ((array) ($validated['selected'] ?? []) as $productId) {
                $copies[(int) $productId] = (int) (($validated['copies'] ?? [])[$productId] ?? 1);
            }
            $labels = $this->labels->forProducts($copies);
        }

        return view('inventory.labels.print', compact('labels'));
    }
}
