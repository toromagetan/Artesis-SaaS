<?php

namespace App\Modules\Platform\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Models\TenantBillingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penagihan sederhana (M5): transfer manual + konfirmasi platform.
 *
 * Harga bulanan efektif = harga paket + harga cabang tambahan ×
 * cabang di luar cabang pertama (data dari panel platform — untuk
 * tangga CEO: hanya Best Offer yang mengenakan biaya cabang tambahan,
 * Rp99 rb). Total tagihan = harga bulanan × jumlah bulan, dibekukan
 * di permintaan.
 */
class BillingService
{
    public function __construct(private readonly PlanService $plans) {}

    /** Harga satu bulan untuk tenant dengan jumlah cabang tertentu. */
    public function monthlyTotal(Plan $plan, int $branchCount): int
    {
        $extra = max(0, $branchCount - 1);

        return (int) $plan->price_monthly + $extra * (int) $plan->extra_branch_price;
    }

    /**
     * Buat permintaan naik paket (status pending). Ditolak bila paket
     * gratis/tidak aktif atau masih ada permintaan menunggu.
     *
     * @throws ValidationException
     */
    public function createRequest(Tenant $tenant, Plan $plan, int $months, ?string $note, User $actor): TenantBillingRequest
    {
        if (! $plan->is_active || (int) $plan->price_monthly <= 0) {
            throw ValidationException::withMessages([
                'plan_id' => __('ui.billing.plan_not_billable'),
            ]);
        }
        if ($months < 1 || $months > 12) {
            throw ValidationException::withMessages([
                'months' => __('ui.billing.months_range'),
            ]);
        }

        // Konkurensi (B-01): pemeriksaan pending + pembuatan harus
        // atomik. Baris tenant dikunci lebih dulu sehingga seluruh
        // mutasi billing per tenant terserialisasi; lapisan kedua
        // adalah constraint unik parsial di database (kolom
        // tergenerasi pending_tenant_id) yang menolak pending kedua
        // bahkan bila jalur aplikasi lain yang membuatnya.
        return DB::transaction(function () use ($tenant, $plan, $months, $note) {
            Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();

            $pending = $tenant->billingRequests()
                ->where('status', TenantBillingRequest::STATUS_PENDING)->exists();
            if ($pending) {
                throw ValidationException::withMessages([
                    'plan_id' => __('ui.billing.pending_exists'),
                ]);
            }

            $branchCount = max(1, $tenant->branches()->count());
            $extra = max(0, $branchCount - 1);
            $amount = $this->monthlyTotal($plan, $branchCount) * $months;

            return TenantBillingRequest::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'extra_branches' => $extra,
                'months' => $months,
                'amount' => $amount,
                'status' => TenantBillingRequest::STATUS_PENDING,
                'note' => $note,
            ]);
        });
    }

    /**
     * Konfirmasi pembayaran: tetapkan paket ke tenant (langganan baru
     * berakhir hari ini + jumlah bulan) dan tutup permintaan.
     */
    public function confirm(TenantBillingRequest $request, User $actor): TenantBillingRequest
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => __('ui.billing.not_pending'),
            ]);
        }

        // Konkurensi (B-01): status diperiksa ULANG di dalam
        // transaksi sesudah baris tenant + request dikunci — dua
        // konfirmasi bersamaan tidak lagi sama-sama lolos; yang
        // kedua membaca status confirmed dan ditolak, sehingga
        // paket hanya diterapkan satu kali.
        return DB::transaction(function () use ($request, $actor) {
            $fresh = $this->lockForDecision($request);
            if (! $fresh->isPending()) {
                throw ValidationException::withMessages([
                    'status' => __('ui.billing.not_pending'),
                ]);
            }

            $subscription = $this->plans->assignPlan(
                $fresh->tenant,
                $fresh->plan,
                now()->addMonths($fresh->months),
                $actor,
            );

            $fresh->update([
                'status' => TenantBillingRequest::STATUS_CONFIRMED,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'subscription_id' => $subscription->id,
            ]);

            return $fresh;
        });
    }

    /** Tolak permintaan: paket tenant tidak berubah. */
    public function reject(TenantBillingRequest $request, User $actor): TenantBillingRequest
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => __('ui.billing.not_pending'),
            ]);
        }

        // Pola kunci yang sama dengan confirm(): keputusan tolak
        // dan konfirmasi yang bersamaan tidak dapat sama-sama
        // berlaku — yang kalah membaca status final dan ditolak.
        return DB::transaction(function () use ($request, $actor) {
            $fresh = $this->lockForDecision($request);
            if (! $fresh->isPending()) {
                throw ValidationException::withMessages([
                    'status' => __('ui.billing.not_pending'),
                ]);
            }

            $fresh->update([
                'status' => TenantBillingRequest::STATUS_REJECTED,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]);

            return $fresh;
        });
    }

    /**
     * Kunci baris tenant lalu baris request (urutan kunci selalu
     * sama di semua mutasi billing agar tidak buntu/deadlock) dan
     * kembalikan salinan request yang segar dari database.
     */
    private function lockForDecision(TenantBillingRequest $request): TenantBillingRequest
    {
        Tenant::whereKey($request->tenant_id)->lockForUpdate()->firstOrFail();

        return TenantBillingRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
    }
}
