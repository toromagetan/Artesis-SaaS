<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Sales\Models\OfflineException;
use App\Modules\Sales\Services\OfflineSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Antrean pengecualian sinkronisasi offline (F5.3) — daftar
 * terpagar cabang aktor; aksi Coba Lagi menjalankan ulang niat
 * lewat jalur penjualan yang sama; Buang hanya Owner/Admin.
 */
class OfflineExceptionController extends Controller
{
    public function index(Request $request): View
    {
        $context = BranchContext::for($request->user());
        $exceptions = OfflineException::with(['device', 'branch'])
            ->when(! $context->hasGlobalAccess(), fn ($q) => $q->whereIn('branch_id', $context->managedBranchIds()))
            ->orderByRaw("FIELD(status, 'pending', 'resolved', 'discarded')")
            ->latest('id')
            ->paginate(20);

        return view('sales.offline-exceptions.index', ['exceptions' => $exceptions]);
    }

    public function retry(Request $request, OfflineException $exception, OfflineSyncService $sync): RedirectResponse
    {
        $actor = $request->user();
        BranchContext::for($actor)->assertCanAccess($exception->branch_id);
        if ($exception->status !== 'pending') {
            return redirect()->route('offline-exceptions.index');
        }
        $sale = $sync->retry($actor, $exception);

        return redirect()->route('offline-exceptions.index')
            ->with('status', $sale !== null
                ? __('ui.offline.retry_success', ['number' => $sale->number])
                : __('ui.offline.retry_failed'));
    }

    public function discard(Request $request, OfflineException $exception, OfflineSyncService $sync): RedirectResponse
    {
        $actor = $request->user();
        BranchContext::for($actor)->assertCanAccess($exception->branch_id);
        if (! $actor->hasRole('Owner') && ! $actor->hasRole('Admin')) {
            abort(403);
        }
        $validated = $request->validate(['note' => ['required', 'string', 'max:500']]);
        $sync->discard($actor, $exception, $validated['note']);

        return redirect()->route('offline-exceptions.index')->with('status', __('ui.offline.discarded'));
    }
}
