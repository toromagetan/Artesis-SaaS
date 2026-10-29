<?php

namespace App\Modules\Comms\Services;

use App\Modules\Comms\Models\WaMessage;
use App\Modules\CRM\Models\Customer;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Services\PlanService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleInstallment;
use App\Support\Money;

/**
 * Otomasi pesan WhatsApp (F3.5, Blueprint D.9).
 *
 * Semua jalur memakai MessagingService (log 100% + opt-out + antrean).
 * Setiap pengiriman dibungkus tangkapan galat: kegagalan gateway /
 * template hilang TIDAK PERNAH menggagalkan transaksi pemicu maupun
 * menghentikan sisa batch terjadwal.
 */
class WaAutomationService
{
    public function __construct(private readonly MessagingService $messages) {}

    /** Invoice transaksi -> WA pelanggan (pemicu: SaleCompleted). */
    public function sendInvoiceForSale(Sale $sale): void
    {
        if (! $this->whatsappAllowed($sale->branch)) {
            return;
        }
        $customer = $sale->customer;
        if ($customer === null || trim((string) $customer->phone) === '') {
            return;
        }
        try {
            $this->messages->send('invoice', $customer, [
                'nomor' => $sale->number,
                'total' => Money::fromDecimal((string) $sale->total_amount)->format(),
                'cabang' => $sale->branch?->name ?? '',
            ], 'invoice');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Pengingat pengambilan -> WA pelanggan (pemicu: proses lensa Complete). */
    public function sendPickupReminder(LensProcess $process): void
    {
        $sale = $process->sale;
        $customer = $sale?->customer;
        if ($sale === null || $customer === null || trim((string) $customer->phone) === '') {
            return;
        }
        if (! $this->whatsappAllowed($sale->branch)) {
            return;
        }
        try {
            $this->messages->send('pickup_reminder', $customer, [
                'nomor' => $sale->number,
                'cabang' => $sale->branch?->name ?? '',
            ], 'reminder');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Sapaan purnajual H+7 sesudah proses lensa Complete.
     * Anti-ganda: satu pesan per transaksi (nomor di badan pesan).
     *
     * @return int jumlah pesan yang diantrekan sesi ini
     */
    public function runAfterService(): int
    {
        $sent = 0;
        $processes = LensProcess::with(['sale.customer', 'sale.branch'])
            ->where('status', 'done')
            ->whereDate('completed_at', today()->subDays(7))
            ->get();

        foreach ($processes as $process) {
            $sale = $process->sale;
            $customer = $sale?->customer;
            if ($sale === null || $customer === null || trim((string) $customer->phone) === '') {
                continue;
            }
            if (! $this->whatsappAllowed($sale->branch)) {
                continue;
            }
            $already = WaMessage::where('customer_id', $customer->id)
                ->where('template_key', 'after_service')
                ->where('body', 'like', '%'.$sale->number.'%')
                ->exists();
            if ($already) {
                continue;
            }
            try {
                $this->messages->send('after_service', $customer, [
                    'nomor' => $sale->number,
                    'cabang' => $sale->branch?->name ?? '',
                ], 'reminder');
                $sent++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $sent;
    }

    /**
     * Ucapan ulang tahun untuk pelanggan yang berulang tahun hari ini.
     * Anti-ganda: maksimal satu ucapan per pelanggan per tahun kalender.
     *
     * @return int jumlah pesan yang diantrekan sesi ini
     */
    /**
     * Pengingat cicilan: angsuran belum lunas yang jatuh tempo besok
     * sampai yang lewat paling lama 7 hari (audit 23 Okt 2026 C4).
     * Anti-ganda per angsuran per hari (pola yang sama dengan
     * purnajual: jejak pada badan pesan terkirim).
     */
    public function runInstallmentReminders(): int
    {
        $sent = 0;
        $installments = SaleInstallment::with(['sale.customer', 'sale.branch'])
            ->where('status', '!=', 'paid')
            ->whereDate('due_date', '<=', today()->addDay())
            ->whereDate('due_date', '>=', today()->subDays(7))
            ->get();

        foreach ($installments as $installment) {
            $sale = $installment->sale;
            $customer = $sale?->customer;
            if ($sale === null || $customer === null || trim((string) $customer->phone) === '') {
                continue;
            }
            if (! $this->whatsappAllowed($sale->branch)) {
                continue;
            }
            $already = WaMessage::where('customer_id', $customer->id)
                ->where('template_key', 'installment_reminder')
                ->where('body', 'like', '%'.$sale->number.'%')
                ->where('body', 'like', '%ke-'.$installment->sequence.' sebesar%')
                ->whereDate('created_at', today())
                ->exists();
            if ($already) {
                continue;
            }
            try {
                $this->messages->send('installment_reminder', $customer, [
                    'nomor' => $sale->number,
                    'angsuran' => (string) $installment->sequence,
                    'jumlah' => $installment->remaining()->format(),
                    'tanggal' => $installment->due_date->format('d/m/Y'),
                    'cabang' => $sale->branch?->name ?? '',
                ], 'installment');
                $sent++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $sent;
    }

    public function runBirthdays(): int
    {
        $sent = 0;
        $customers = Customer::with('branch')
            ->where('is_active', true)
            ->whereNotNull('birth_date')
            ->whereMonth('birth_date', today()->month)
            ->whereDay('birth_date', today()->day)
            ->get();

        foreach ($customers as $customer) {
            if (trim((string) $customer->phone) === '') {
                continue;
            }
            if (! $this->whatsappAllowed($customer->branch)) {
                continue;
            }
            $already = WaMessage::where('customer_id', $customer->id)
                ->where('template_key', 'birthday')
                ->whereYear('created_at', today()->year)
                ->exists();
            if ($already) {
                continue;
            }
            try {
                $this->messages->send('birthday', $customer, [
                    'cabang' => $customer->branch?->name ?? '',
                ], 'birthday');
                $sent++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $sent;
    }

    /**
     * M4: WhatsApp adalah fitur paket — tenant cabang tanpa fitur
     * whatsapp tidak menerima kiriman otomatis apa pun. Cabang tanpa
     * tenant (instalasi mandiri) selalu diizinkan.
     */
    private function whatsappAllowed(?Branch $branch): bool
    {
        $tenant = $branch?->tenant;

        return $tenant === null
            || app(PlanService::class)->featureEnabled($tenant, 'whatsapp');
    }
}
