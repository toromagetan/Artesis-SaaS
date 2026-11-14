<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Services\ProductService;
use App\Modules\Purchasing\Models\Receipt;
use App\Support\Barcode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Label harga & barcode (F4.4, PRD-Detail 7.6): menyusun daftar
 * label dari pilihan detail produk (dengan jumlah salinan per
 * detail) atau dari satu batch penerimaan (salinan = qty diterima
 * × pengali). Barcode detail 12/13 digit numerik dicetak EAN-13;
 * selain itu Code 128 dari barcode, atau dari SKU bila kosong.
 * A1: label menempel pada DETAIL (SKU/barcode/harga milik detail).
 */
class LabelService
{
    /**
     * @param  array<string, int>  $copiesByDetailKey  "alias:id" => jumlah label
     * @return array<int, array<string, mixed>>
     */
    public function forProducts(array $copiesByDetailKey): array
    {
        $copiesByDetailKey = array_filter(
            $copiesByDetailKey,
            fn ($copies) => (int) $copies > 0,
        );
        if ($copiesByDetailKey === []) {
            throw ValidationException::withMessages(['items' => __('ui.labels.nothing_selected')]);
        }
        $service = app(ProductService::class);
        $labels = [];
        foreach ($copiesByDetailKey as $key => $copies) {
            [$type, $id] = array_pad(explode(':', (string) $key, 2), 2, null);
            if ($type === null || $id === null) {
                continue;
            }
            try {
                $detail = $service->findDetail($type, (int) $id);
            } catch (\Throwable) {
                continue;
            }
            for ($i = 0, $n = min((int) $copies, 500); $i < $n; $i++) {
                $labels[] = $this->entryFor($detail);
            }
        }

        return $labels;
    }

    /** @return array<int, array<string, mixed>> */
    public function forReceipt(Receipt $receipt, int $multiplier = 1): array
    {
        $receipt->loadMissing('items.purchaseOrderItem.detail');
        $labels = [];
        foreach ($receipt->items as $item) {
            $detail = $item->purchaseOrderItem?->detail;
            if ($detail === null) {
                continue;
            }
            $count = min(max(1, $multiplier), 50) * (int) $item->qty;
            for ($i = 0, $n = min($count, 2000); $i < $n; $i++) {
                $labels[] = $this->entryFor($detail);
            }
        }
        if ($labels === []) {
            throw ValidationException::withMessages(['receipt_id' => __('ui.labels.nothing_selected')]);
        }

        return $labels;
    }

    /** Satu entri label: nama master, SKU, harga, SVG barcode + teksnya. */
    public function entryFor(Model $detail): array
    {
        $raw = trim((string) ($detail->barcode ?: $detail->sku));
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
            'name' => $detail->product?->name ?? '',
            'sku' => $detail->sku,
            'price' => $detail->sale_price,
            'barcode_text' => $raw,
            'format' => $format,
            'svg' => $svg,
        ];
    }
}
