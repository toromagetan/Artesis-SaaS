<?php

namespace App\Modules\Comms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\Comms\Services\MessagingService;
use App\Modules\CRM\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Inbox WhatsApp dua arah (F3.8). Pesan masuk ditangkap webhook F3.4
 * sebagai baris direction 'in' tertaut pelanggan; halaman ini
 * mengelompokkannya per percakapan, menandai terbaca saat dibuka,
 * dan membalas lewat MessagingService (tercatat + terantre).
 */
class WaInboxController extends Controller
{
    public function index(): View
    {
        $latestIds = WaMessage::whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->selectRaw('MAX(id) as latest_id')
            ->pluck('latest_id');

        $conversations = WaMessage::with('customer')
            ->whereIn('id', $latestIds)
            ->orderByDesc('id')
            ->get();

        $unread = WaMessage::where('direction', 'in')
            ->whereNull('read_at')
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) as total')
            ->pluck('total', 'customer_id');

        $orphans = WaMessage::where('direction', 'in')
            ->whereNull('customer_id')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('comms/wa-inbox', compact('conversations', 'unread', 'orphans'));
    }

    public function show(Customer $customer): View
    {
        WaMessage::where('customer_id', $customer->id)
            ->where('direction', 'in')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = WaMessage::where('customer_id', $customer->id)
            ->orderBy('id')
            ->limit(200)
            ->get();

        return view('comms/wa-inbox-show', compact('customer', 'messages'));
    }

    public function reply(Request $request, Customer $customer, MessagingService $messaging): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $messaging->sendText($customer, (string) $validated['body'], 'inbox', $request->user());

        return redirect()->route('wa-inbox.show', $customer)->with('status', __('ui.inbox.replied'));
    }
}
