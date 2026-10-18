<?php

namespace App\Modules\Comms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comms\Models\WaMessage;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Log kirim WhatsApp (F3.4): semua pesan keluar & masuk dengan
 * statusnya. Terpagar wa.send. Filter status/pemicu + cari nomor/nama.
 */
class WaLogController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $trigger = (string) $request->query('pemicu', '');

        $messages = WaMessage::with(['customer', 'sender'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('phone', 'like', "%{$q}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%"));
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($trigger !== '', fn ($query) => $query->where('trigger', $trigger))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('comms.wa-log', compact('messages', 'q', 'status', 'trigger'));
    }
}
