<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Purchasing\Models\Receipt;
use App\Support\Barcode;
use Illuminate\Validation\ValidationException;

/**
 * Label harga & barcode (F4.4, PRD-Detail 7.6): menyusun daftar
 * label dari pilihan produk (dengan jumlah salinan per produk)
 * atau dari satu batch penerimaan (salinan = qty diterima ×
 * pengali). Barcode produk 12/13 digit numerik dicetak EAN-13;
 * selain itu Code 128 dari barcode, atau dari SKU bila kosong.
 */
class LabelService
{
    /**
     * @param  array<int, int>  $copiesByProductId  product_id => jumlah label
     * @return array<int, array<string, mixed>>
     */
    public function forProducts(array $copiesByProductId): array
    {
        $copiesByProductId = array_filter(
            $copiesByProductId,
            fn ($copies) => (int) $copies > 0,
        );
        if ($copiesByProductId === []) {
            throw ValidationException::withMessages(['items' => __('ui.labels.nothing_selected')]);
        }
        $products = Product::whereIn('id', array_keys($copiesByProductId))->get()->keyBy('id');
        $labels = [];
        foreach ($copiesByProductId as $productId => $copies) {
            $product = $products->get((int) $productId);
            if ($product === null) {
                continue;
            }
            for ($i = 0, $n = min((int) $copies, 500); $i < $n; $i++) {
                $labels[] = $this->entryFor($product);
            }
        }

        return $labels;
    }

    /** @return array<int, array<string, mixed>> */
    public function forReceipt(Receipt $receipt, int $multiplier = 1): array
    {
        $receipt->loadMissing('items.purchaseOrderItem.product');
        $labels = [];
        foreach ($receipt->items as $item) {
            $product = $item->purchaseOrderItem?->product;
            if ($product === null) {
                continue;
            }
            $count = min(max(1, $multiplier), 50) * (int) $item->qty;
            for ($i = 0, $n = min($count, 2000); $i < $n; $i++) {
                $labels[] = $this->entryFor($product);
            }
        }
        if ($labels === []) {
            throw ValidationException::withMessages(['receipt_id' => __('ui.labels.nothing_selected')]);
        }

        return $labels;
    }

    /** Satu entri label: nama, SKU, harga, SVG barcode + teksnya. */
    public function entryFor(Product $product): array
    {
        $raw = trim((string) ($product->barcode ?: $product->sku));
        $format = 'code128';
        $svg = null;
        if (preg_match('/^\d{12,13}$/', $raw) === 1) {
            try {
                $svg = Barcode::ean13Svg(Barcode::normalizeEan13($raw));
                $raw = Barcode::normalizeEan13($raw);
                $format = 'ean13';
            } catch (\InvalidArgumentException) {
                $svg = null;
            }
        }
        if ($svg === null) {
            $svg = Barcode::code128Svg($raw);
        }

        return [
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => $product->sale_price,
            'barcode_text' => $raw,
            'format' => $format,
            'svg' => $svg,
        ];
    }
}
