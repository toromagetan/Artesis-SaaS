<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Halaman klien kasir offline (F5.3b) — aplikasi satu halaman
 * yang bekerja dari snapshot lokal (localStorage) dan antrean
 * lokal; seluruh logika sinkronisasi berbicara ke API perangkat
 * (/api/v1/offline/*) dengan token perangkat yang diatur staf.
 */
class OfflineCashierController extends Controller
{
    public function show(): View
    {
        $keys = ['online', 'offline', 'stock_of', 'over_stock', 'queued', 'synced', 'duplicate',
            'exception', 'need_setup', 'need_snapshot', 'need_customer', 'need_items',
            'sync_failed', 'snapshot_ok', 'remove'];
        $str = collect($keys)->mapWithKeys(fn (string $k) => [$k => __('ui.offline_cashier.'.$k)])->all();

        return view('sales.offline-cashier', ['str' => $str]);
    }
}
