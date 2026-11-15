<?php

namespace App\Support;

use App\Modules\Comms\Services\MessagingService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Master\Models\Setting;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Sales\Models\Sale;

/**
 * Tautan Kirim WA (wa.me) — skema WhatsApp tenant sejak keputusan
 * owner 15 Nov 2026. Tautan membuka aplikasi WhatsApp staf (HP atau
 * desktop) pada ruang chat pelanggan/pemasok dengan teks yang sudah
 * disusun dari data transaksi; staf menekan kirim sendiri di aplikasi
 * WA, sehingga tidak ada biaya API dan tidak ada aturan jendela
 * 24 jam. Dokumen (invoice, resep, PO) dilampirkan staf dari berkas
 * hasil Unduh PDF.
 */
class WaLink
{
    /** URL wa.me untuk nomor + teks; null bila nomor tidak layak. */
    public static function url(?string $phone, string $text): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $normalized = MessagingService::normalizePhone($phone);

        if (strlen($normalized) < 9) {
            return null;
        }

        return 'https://wa.me/'.$normalized.'?text='.rawurlencode($text);
    }

    public static function forCustomer(Customer $customer, string $text): ?string
    {
        return self::url($customer->phone, $text);
    }

    public static function storeName(): string
    {
        return (string) Setting::value('store.name', config('app.name', 'Artesis'));
    }

    /** Sapaan umum dari halaman pelanggan. */
    public static function greetingText(Customer $customer): string
    {
        return __('ui.wa_link.greeting', [
            'name' => $customer->name,
            'store' => self::storeName(),
        ]);
    }

    /** Ringkasan invoice untuk tombol Kirim WA di penjualan. */
    public static function saleInvoiceText(Sale $sale): string
    {
        return __('ui.wa_link.sale_invoice', [
            'customer' => $sale->customer->name,
            'store' => self::storeName(),
            'number' => $sale->number,
            'total' => Money::fromDecimal((string) $sale->total_amount)->format(),
            'paid' => Money::fromDecimal((string) $sale->paid_amount)->format(),
            'remaining' => $sale->remaining()->format(),
            'status' => __('ui.sales.pay_'.$sale->paymentStatus()),
        ]);
    }

    /** Kabar pesanan siap diambil (proses lensa selesai). */
    public static function pickupReadyText(Sale $sale): string
    {
        return __('ui.wa_link.pickup_ready', [
            'customer' => $sale->customer->name,
            'store' => self::storeName(),
            'number' => $sale->number,
            'branch' => $sale->branch->name,
        ]);
    }

    /** Kabar proses masih berjalan (mis. konfirmasi lab hari ke-2). */
    public static function processProgressText(LensProcess $process): string
    {
        $sale = $process->sale;
        $estimate = null;

        if ($process->estimated_days) {
            $base = $process->started_at ?? $process->created_at;
            if ($base !== null) {
                $estimate = $base->copy()->addDays((int) $process->estimated_days)->format('d/m/Y');
            }
        }

        return __($estimate !== null ? 'ui.wa_link.process_progress' : 'ui.wa_link.process_progress_no_estimate', [
            'customer' => $sale->customer->name,
            'store' => self::storeName(),
            'number' => $sale->number,
            'estimate' => $estimate,
        ]);
    }

    /** Teks pendamping lampiran PO untuk pemasok. */
    public static function purchaseOrderText(PurchaseOrder $po): string
    {
        return __('ui.wa_link.po_order', [
            'supplier' => $po->supplier->name,
            'store' => self::storeName(),
            'number' => $po->number,
        ]);
    }
}
