<?php

namespace App\Modules\Platform\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Setting;
use App\Modules\Platform\Models\PaygCreditLedger;
use App\Modules\Platform\Models\PaygCreditRequest;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Sales\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saldo transaksi PAYG — bayar per transaksi penjualan (keputusan
 * owner 2026-11-15, model murni Rp500/transaksi sebagai paket
 * TAMBAHAN). Tenant membeli kredit transaksi (prabayar): ajukan
 * isi ulang → transfer → platform mengonfirmasi → saldo bertambah
 * di buku besar. Satu penjualan BERHASIL tersimpan memotong 1
 * kredit; void penuh membalikkannya.
 *
 * Beda disengaja dari kredit AI: saldo BOLEH negatif. Penjualan
 * adalah fungsi inti kasir — saldo habis tidak pernah memblokir
 * transaksi; kekurangannya tercatat sebagai tunggakan yang
 * terpotong dari isi ulang berikutnya.
 *
 * Konkurensi meniru gerbang penagihan B-01: setiap mutasi saldo
 * berjalan di transaksi yang mengunci baris tenant lebih dulu,
 * sehingga potongan & konfirmasi terserialisasi per tenant.
 * Potongan per penjualan idempoten: baris usage per sale_id unik
 * (diperiksa di service DAN ditegakkan indeks unik database).
 */
class PaygCreditService
{
    public const PRICE_KEY = 'payg_price_per_transaction';

    public const DEFAULT_PRICE = 500;

    public const MIN_TOPUP = 10;

    public const MAX_TOPUP = 10000;

    /** Harga per transaksi (Rp) dari pengaturan platform; default terbaca bila belum diatur. */
    public function pricePerTransaction(): int
    {
        $value = (int) (Setting::value(self::PRICE_KEY) ?? self::DEFAULT_PRICE);

        return $value > 0 ? $value : self::DEFAULT_PRICE;
    }

    /** Saldo transaksi tenant saat ini (0 bila belum pernah bergerak; negatif = tunggakan). */
    public function balance(Tenant $tenant): int
    {
        $last = PaygCreditLedger::where('tenant_id', $tenant->id)
            ->orderByDesc('id')
            ->first();

        return $last === null ? 0 : (int) $last->balance_after;
    }

    /**
     * Ajukan isi ulang: harga dibekukan dari pengaturan saat ini.
     * Ditolak bila masih ada pengajuan menunggu.
     *
     * @throws ValidationException
     */
    public function requestTopup(Tenant $tenant, int $credits, User $actor): PaygCreditRequest
    {
        if ($credits < self::MIN_TOPUP || $credits > self::MAX_TOPUP) {
            throw ValidationException::withMessages([
                'credits' => __('ui.payg_credits.credits_range', ['min' => self::MIN_TOPUP, 'max' => self::MAX_TOPUP]),
            ]);
        }

        return DB::transaction(function () use ($tenant, $credits) {
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();

            $pending = $tenant->paygCreditRequests()
                ->where('status', PaygCreditRequest::STATUS_PENDING)->exists();
            if ($pending) {
                throw ValidationException::withMessages([
                    'credits' => __('ui.payg_credits.pending_exists'),
                ]);
            }

            $price = $this->pricePerTransaction();

            return PaygCreditRequest::create([
                'tenant_id' => $tenant->id,
                'credits' => $credits,
                'unit_price' => $price,
                'amount' => $price * $credits,
                'status' => PaygCreditRequest::STATUS_PENDING,
            ]);
        });
    }

    /**
     * Konfirmasi platform: saldo bertambah tepat satu kali —
     * status diperiksa ulang sesudah baris tenant + pengajuan
     * dikunci, sehingga konfirmasi ganda ditolak.
     *
     * @throws ValidationException
     */
    public function confirm(PaygCreditRequest $request, User $actor): PaygCreditRequest
    {
        return DB::transaction(function () use ($request, $actor) {
            Tenant::whereKey($request->tenant_id)->lockForUpdate()->firstOrFail();
            $fresh = PaygCreditRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! $fresh->isPending()) {
                throw ValidationException::withMessages([
                    'status' => __('ui.payg_credits.not_pending'),
                ]);
            }

            $balance = $this->balance($fresh->tenant) + $fresh->credits;
            PaygCreditLedger::create([
                'tenant_id' => $fresh->tenant_id,
                'kind' => 'topup',
                'credits_delta' => $fresh->credits,
                'balance_after' => $balance,
                'unit_price' => $fresh->unit_price,
                'amount' => $fresh->amount,
                'request_id' => $fresh->id,
                'actor_id' => $actor->id,
            ]);

            $fresh->update([
                'status' => PaygCreditRequest::STATUS_CONFIRMED,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);

            return $fresh;
        });
    }

    /** @throws ValidationException */
    public function reject(PaygCreditRequest $request, User $actor): PaygCreditRequest
    {
        return DB::transaction(function () use ($request, $actor) {
            Tenant::whereKey($request->tenant_id)->lockForUpdate()->firstOrFail();
            $fresh = PaygCreditRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! $fresh->isPending()) {
                throw ValidationException::withMessages([
                    'status' => __('ui.payg_credits.not_pending'),
                ]);
            }

            $fresh->update([
                'status' => PaygCreditRequest::STATUS_REJECTED,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);

            return $fresh;
        });
    }

    /**
     * Potong 1 kredit untuk satu penjualan yang berhasil tersimpan.
     * Idempoten: penjualan yang sudah punya baris usage tidak
     * dipotong lagi (sinkronisasi offline ganda aman). Saldo boleh
     * menjadi negatif — fungsi ini TIDAK pernah menolak penjualan.
     */
    public function chargeForSale(Tenant $tenant, Sale $sale, User $actor): void
    {
        DB::transaction(function () use ($tenant, $sale, $actor) {
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();

            $charged = PaygCreditLedger::where('tenant_id', $tenant->id)
                ->where('kind', 'usage')
                ->where('sale_id', $sale->id)
                ->exists();
            if ($charged) {
                return;
            }

            PaygCreditLedger::create([
                'tenant_id' => $tenant->id,
                'kind' => 'usage',
                'credits_delta' => -1,
                'balance_after' => $this->balance($tenant) - 1,
                'sale_id' => $sale->id,
                'actor_id' => $actor->id,
            ]);
        });
    }

    /**
     * Balikkan potongan penjualan yang di-void penuh: tepat satu
     * baris reversal per penjualan, dan hanya bila potongannya ada.
     */
    public function reverseForSale(Tenant $tenant, Sale $sale, User $actor): void
    {
        DB::transaction(function () use ($tenant, $sale, $actor) {
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();

            $charged = PaygCreditLedger::where('tenant_id', $tenant->id)
                ->where('kind', 'usage')
                ->where('sale_id', $sale->id)
                ->exists();
            $reversed = PaygCreditLedger::where('tenant_id', $tenant->id)
                ->where('kind', 'reversal')
                ->where('sale_id', $sale->id)
                ->exists();
            if (! $charged || $reversed) {
                return;
            }

            PaygCreditLedger::create([
                'tenant_id' => $tenant->id,
                'kind' => 'reversal',
                'credits_delta' => 1,
                'balance_after' => $this->balance($tenant) + 1,
                'sale_id' => $sale->id,
                'actor_id' => $actor->id,
            ]);
        });
    }
}
