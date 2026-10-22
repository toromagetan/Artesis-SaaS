<?php

namespace App\Modules\Comms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comms\Exceptions\GatewayException;
use App\Modules\Comms\Models\WaGateway;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\Comms\Services\GatewayFactory;
use App\Modules\Comms\Services\MessagingService;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pengaturan Gateway WA (F3.4, PRD Komunikasi): penyedia, kredensial
 * (tersimpan terenkripsi, tidak pernah tampil penuh), nomor pengirim,
 * status koneksi & tombol uji kirim. Terpagar settings.manage.
 * Token yang dikosongkan saat simpan = token lama dipertahankan.
 */
class WaGatewayController extends Controller
{
    public function edit(): View
    {
        $gateway = WaGateway::orderBy('id')->first();

        return view('comms.wa-gateway', compact('gateway'));
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', Rule::in(['meta_cloud_api'])],
            'sender_number' => ['required', 'string', 'max:30'],
            'phone_number_id' => ['required', 'string', 'max:40'],
            'waba_id' => ['nullable', 'string', 'max:40'],
            'access_token' => ['nullable', 'string', 'max:500'],
            'verify_token' => ['nullable', 'string', 'max:120'],
            'app_secret' => ['nullable', 'string', 'max:200'],
            'api_version' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $gateway = WaGateway::orderBy('id')->first() ?? new WaGateway;
        $credentials = $gateway->credentials();
        $credentials['phone_number_id'] = $validated['phone_number_id'];
        $credentials['waba_id'] = $validated['waba_id'] ?? '';
        $credentials['api_version'] = ($validated['api_version'] ?? '') ?: 'v21.0';
        if (! empty($validated['access_token'])) {
            $credentials['access_token'] = $validated['access_token'];
        }
        if (! empty($validated['verify_token'])) {
            $credentials['verify_token'] = $validated['verify_token'];
        }
        if (! empty($validated['app_secret'])) {
            $credentials['app_secret'] = $validated['app_secret'];
        }
        $credentials['access_token'] ??= '';
        $credentials['verify_token'] ??= '';
        $credentials['app_secret'] ??= '';

        $gateway->fill([
            'provider' => $validated['provider'],
            'sender_number' => MessagingService::normalizePhone($validated['sender_number']),
            'credentials_encrypted' => $credentials,
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ])->save();

        // Audit TANPA nilai kredensial — hanya nama kolom yang berubah.
        $audit->log(
            action: 'wa.gateway.update',
            subjectType: 'wa_gateway',
            subjectId: $gateway->id,
            newValues: [
                'provider' => $gateway->provider,
                'sender_number' => $gateway->sender_number,
                'is_active' => $gateway->is_active,
                'credential_fields' => array_keys($credentials),
            ],
            actor: $request->user(),
        );

        return redirect()->route('wa-gateway.edit')->with('status', __('ui.wa.gateway_saved'));
    }

    /** Uji kirim: satu pesan teks nyata ke nomor tujuan lewat gateway aktif. */
    public function test(Request $request, GatewayFactory $factory): RedirectResponse
    {
        $validated = $request->validate([
            'test_phone' => ['required', 'string', 'max:30'],
        ]);

        $gateway = $factory->active();
        if ($gateway === null) {
            return back()->with('test_result', __('ui.wa.test_no_gateway'));
        }

        $message = WaMessage::create([
            'customer_id' => null,
            'phone' => MessagingService::normalizePhone($validated['test_phone']),
            'template_key' => null,
            'body' => __('ui.wa.test_body'),
            'direction' => 'out',
            'status' => 'queued',
            'trigger' => 'test',
            'sent_by' => $request->user()->id,
        ]);

        try {
            $providerId = $gateway->send($message);
            $message->update([
                'status' => 'sent',
                'provider_message_id' => $providerId,
                'sent_at' => now(),
                'error' => null,
            ]);

            return back()->with('test_result', __('ui.wa.test_success'));
        } catch (GatewayException $e) {
            $message->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 255)]);

            return back()->with('test_result', __('ui.wa.test_failed').' — '.$e->getMessage());
        }
    }
}
