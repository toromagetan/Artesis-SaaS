<?php

namespace App\Modules\Comms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\Comms\Services\MessagingService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Inbox WhatsApp dua arah (F3.8). Pesan masuk ditangkap webhook F3.4
 * sebagai baris direction 'in' tertaut pelanggan; halaman ini
 * mengelompokkannya per percakapan, menandai terbaca saat dibuka,
 * dan membalas lewat MessagingService (tercatat + terantre).
 */
#[Middleware('plan.feature:whatsapp')]
class WaInboxController extends Controller
{
    public function index(): View
    {
        // Isolasi tenant (koreksi audit PR #33): WaMessage sendiri
        // tidak bertenant — pagarnya adalah kepemilikan pelanggan.
        // Query Customer terpagar TenantScope mengikuti aktor
        // (tenant: hanya pelanggannya; standalone: ember NULL;
        // platform: semua), sehingga percakapan tenant lain tidak
        // pernah tampil di inbox aktor.
        $ownCustomerIds = Customer::query()->pluck('id');

        $latestIds = WaMessage::whereNotNull('customer_id')
            ->whereIn('customer_id', $ownCustomerIds)
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
            ->whereIn('customer_id', $ownCustomerIds)
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) as total')
            ->pluck('total', 'customer_id');

        // Pesan yatim (tanpa atribusi — termasuk nomor ambigu
        // lintas tenant dari webhook) hanya terlihat oleh platform
        // dan instalasi mandiri; bagi aktor tenant pesan itu tidak
        // dapat dipastikan miliknya, jadi tidak ditampilkan.
        $context = TenantContext::current();
        $orphans = ($context->isPlatform() || $context->isStandalone())
            ? WaMessage::where('direction', 'in')
                ->whereNull('customer_id')
                ->orderByDesc('id')
                ->limit(20)
                ->get()
            : collect();

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
