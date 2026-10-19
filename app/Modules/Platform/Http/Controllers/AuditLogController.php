<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Penampil Log Aktivitas (paritas Artesis; celah audit 2026-10-18).
 * Data audit_log sudah ditulis semua modul lewat AuditLogger —
 * modul ini hanya PEMBACA: daftar tersaring + detail halaman
 * penuh. Gerbang settings.manage (sejajar halaman Pengaturan
 * lain); pagar cabang: aktor global melihat semua, aktor
 * terpagar hanya log cabangnya (log tanpa cabang hanya untuk
 * aktor global).
 */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $context = BranchContext::for($request->user());
        $query = AuditLog::with(['user', 'branch'])->latest('id');

        if (! $context->hasGlobalAccess()) {
            $query->whereIn('branch_id', $context->managedBranchIds());
        }
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

        $subjectTypes = AuditLog::query()
            ->when(! $context->hasGlobalAccess(), fn ($q) => $q->whereIn('branch_id', $context->managedBranchIds()))
            ->distinct()->orderBy('subject_type')->pluck('subject_type');

        return view('settings.audit-logs.index', [
            'logs' => $query->paginate(25)->withQueryString(),
            'subjectTypes' => $subjectTypes,
            'users' => User::orderBy('name')->get(['id', 'name', 'username']),
            'branches' => Branch::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['aksi', 'subjek', 'pengguna', 'cabang', 'dari', 'sampai']),
        ]);
    }

    public function show(Request $request, AuditLog $log): View
    {
        $context = BranchContext::for($request->user());
        if (! $context->hasGlobalAccess()
            && ($log->branch_id === null || ! $context->allowsBranch($log->branch_id))) {
            abort(403);
        }
        $log->load(['user', 'branch']);

        return view('settings.audit-logs.show', ['log' => $log]);
    }
}
