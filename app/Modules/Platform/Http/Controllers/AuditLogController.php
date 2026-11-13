<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Penampil Log Aktivitas (paritas Artesis; celah audit 2026-10-18).
 * Data audit_log sudah ditulis semua modul lewat AuditLogger —
 * modul ini hanya PEMBACA: daftar tersaring + detail halaman
 * penuh. Gerbang settings.manage (sejajar halaman Pengaturan
 * lain). Pagar baca (L-01; audit A-04): aktor tenant hanya melihat
 * log tenantnya sendiri — termasuk log TANPA cabang yang dulu
 * tidak terlihat oleh aktor tenant mana pun; baris warisan
 * tenant_id NULL hanya terjangkau lewat cabang terkelola aktor.
 * Aktor non-tenant mempertahankan pagar cabang lama; aktor
 * global non-tenant (instalasi mandiri) melihat semua.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $context = BranchContext::for($request->user());
        $query = AuditLog::with(['user', 'branch'])->latest('id');

        $this->applyVisibility($query, $request->user());
        if ($request->filled('aksi')) {
            $query->where('action', 'like', '%'.$request->string('aksi').'%');
        }
        if ($request->filled('subjek')) {
            $query->where('subject_type', $request->string('subjek'));
        }
        if ($request->filled('pengguna')) {
            $query->where('user_id', $request->integer('pengguna'));
        }
        // Filter cabang hanya MENYEMPITKAN cakupan yang sudah terpagar
        // BranchContext di atas — bukan sumber kewenangan (A.3.5).
        $branchFilter = $request->filled('cabang') ? $request->integer('cabang') : null;
        if ($branchFilter !== null) {
            $query->where('branch_id', $branchFilter);
        }
        if ($request->filled('dari')) {
            $query->whereDate('created_at', '>=', $request->string('dari'));
        }
        if ($request->filled('sampai')) {
            $query->whereDate('created_at', '<=', $request->string('sampai'));
        }

        $subjectTypeQuery = AuditLog::query();
        $this->applyVisibility($subjectTypeQuery, $request->user());
        $subjectTypes = $subjectTypeQuery
            ->distinct()->orderBy('subject_type')->pluck('subject_type');

        return view('settings.audit-logs.index', [
            'logs' => $query->paginate(25)->withQueryString(),
            'subjectTypes' => $subjectTypes,
            // Daftar filter ikut terpagari wilayah aktor: nama staf &
            // cabang tenant lain tidak ditawarkan sebagai pilihan.
            'users' => $this->filterUsers($request->user()),
            'branches' => Branch::whereIn('id', $context->companyBranchIds())->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['aksi', 'subjek', 'pengguna', 'cabang', 'dari', 'sampai']),
        ]);
    }

    public function show(Request $request, AuditLog $log): View
    {
        $visible = AuditLog::query()->whereKey($log->id);
        $this->applyVisibility($visible, $request->user());
        if (! $visible->exists()) {
            abort(403);
        }
        $log->load(['user', 'branch']);

        return view('settings.audit-logs.show', ['log' => $log]);
    }

    /**
     * Pagar visibilitas daftar & detail (L-01). Aktor tenant valid:
     * log tenantnya sendiri (aktor global: semua log tenant itu,
     * termasuk tanpa cabang; aktor terpagar cabang: cabang
     * terkelola atau tanpa cabang) + baris warisan NULL lewat
     * cabang terkelola/perusahaannya. Aktor non-tenant: pagar
     * cabang lama (global = semua).
     */
    private function applyVisibility(Builder $query, User $user): void
    {
        $branchContext = BranchContext::for($user);
        $tenantContext = TenantContext::for($user);

        if ($tenantContext->isValid()) {
            $tenantId = $tenantContext->tenantId();
            if ($branchContext->hasGlobalAccess()) {
                $company = $branchContext->companyBranchIds();
                $query->where(fn ($q) => $q->where('tenant_id', $tenantId)
                    ->orWhere(fn ($q2) => $q2->whereNull('tenant_id')->whereIn('branch_id', $company)));
            } else {
                $managed = $branchContext->managedBranchIds();
                $query->where(fn ($q) => $q
                    ->where(fn ($q2) => $q2->where('tenant_id', $tenantId)
                        ->where(fn ($q3) => $q3->whereIn('branch_id', $managed)->orWhereNull('branch_id')))
                    ->orWhere(fn ($q2) => $q2->whereNull('tenant_id')->whereIn('branch_id', $managed)));
            }

            return;
        }

        if (! $branchContext->hasGlobalAccess()) {
            $query->whereIn('branch_id', $branchContext->managedBranchIds());
        }
    }

    /** Pengguna yang boleh muncul di filter log untuk aktor ini. */
    private function filterUsers(?User $actor)
    {
        // Pagar lima mode TenantContext (putaran koreksi temuan 3):
        // aktor unassigned/tamu memperoleh daftar KOSONG.
        $tenant = $actor !== null ? TenantContext::for($actor) : null;
        $query = User::orderBy('name');
        if ($tenant === null) {
            $query->whereIn('tenant_id', [0]);
        } else {
            $tenant->constrainByTenant($query);
            if ($tenant->isStandalone()) {
                $query->where('is_platform', false);
            }
        }

        return $query->get(['id', 'name', 'username']);
    }
}
