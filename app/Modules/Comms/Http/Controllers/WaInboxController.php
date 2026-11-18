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
        // Isolasi tenant (koreksi audit PR #33, diperkeras verifikasi
        // audit 18 Nov 2026 temuan B): pagarnya GANDA. Query Customer
        // terpagar TenantScope mengikuti aktor (tenant: hanya
        // pelanggannya; standalone: ember NULL; platform: semua),
        // DAN query WaMessage dibatasi cap tenant-nya sendiri
        // (constrainByTenant) — WaMessage bertenant sejak migrasi
        // 000061. Baris pesan yang cap tenant-nya tidak seember
        // dengan pelanggan/aktor (data tak konsisten dari jalur
        // tulis mana pun) tidak tampil dan tidak terhitung.
        $context = TenantContext::current();
        $ownCustomerIds = Customer::query()->pluck('id');

        $latestQuery = WaMessage::whereNotNull('customer_id')
            ->whereIn('customer_id', $ownCustomerIds);
        $context->constrainByTenant($latestQuery);
        $latestIds = $latestQuery
            ->groupBy('customer_id')
            ->selectRaw('MAX(id) as latest_id')
            ->pluck('latest_id');

        $conversations = WaMessage::with('customer')
            ->whereIn('id', $latestIds)
            ->orderByDesc('id')
            ->get();

        $unreadQuery = WaMessage::where('direction', 'in')
            ->whereNull('read_at')
            ->whereNotNull('customer_id')
            ->whereIn('customer_id', $ownCustomerIds);
        $context->constrainByTenant($unreadQuery);
        $unread = $unreadQuery
            ->groupBy('customer_id')
            ->selectRaw('customer_id, COUNT(*) as total')
            ->pluck('total', 'customer_id');

        // Pesan yatim (tanpa atribusi — termasuk nomor ambigu
        // lintas tenant dari webhook) hanya terlihat oleh platform
        // dan instalasi mandiri; bagi aktor tenant pesan itu tidak
        // dapat dipastikan miliknya, jadi tidak ditampilkan.
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
        // Pagar ganda yang sama dengan index(): pesan yang cap
        // tenant-nya tidak seember dengan konteks aktor tidak
        // ditampilkan dan TIDAK ditandai terbaca.
        $context = TenantContext::current();

        $markRead = WaMessage::where('customer_id', $customer->id)
            ->where('direction', 'in')
            ->whereNull('read_at');
        $context->constrainByTenant($markRead);
        $markRead->update(['read_at' => now()]);

        $messageQuery = WaMessage::where('customer_id', $customer->id);
        $context->constrainByTenant($messageQuery);
        $messages = $messageQuery
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
