<?php

namespace App\Modules\Comms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comms\Models\WaGateway;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\Comms\Services\MessagingService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Webhook Meta WhatsApp (F3.4; inbox dibaca F3.8).
 * GET  = jabat tangan verifikasi (hub.verify_token vs kredensial).
 * POST = pembaruan status terkirim (delivered) + tangkap pesan masuk
 *        sebagai baris direction 'in' agar tidak ada pesan hilang.
 * Endpoint publik tanpa sesi; keaslian dijaga verify_token (GET) dan
 * tanda tangan X-Hub-Signature-256 (HMAC-SHA256 atas badan mentah
 * dengan App Secret gateway) pada POST — audit 23 Okt 2026 (A1).
 * Tanpa App Secret terkonfigurasi, POST selalu ditolak (fail-closed).
 *
 * WA per tenant (keputusan owner 2026-11-14, menutup OD-4): gateway
 * pemilik callback dikenali DETERMINISTIK dari
 * metadata.phone_number_id di dalam payload Meta — nomor penerima
 * adalah nomor milik satu tenant. Tanda tangan diverifikasi dengan
 * App Secret gateway ITU, dan pelanggan pengirim hanya dicari di
 * dalam tenant pemilik gateway. Payload tanpa phone_number_id yang
 * cocok jatuh ke jalur lama gateway global (mode mandiri): tanda
 * tangan gateway global + aturan nomor unik se-instalasi.
 */
class WaWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $known = WaGateway::all()
            ->map(fn (WaGateway $g) => $g->credential('verify_token', ''))
            ->filter(fn (string $t) => $t !== '');

        if ($request->query('hub_mode') === 'subscribe' && $token !== ''
            && $known->contains(fn (string $t) => hash_equals($t, $token))) {
            return response((string) $request->query('hub_challenge', $request->query('hub.challenge', '')), 200);
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request): Response
    {
        $entries = (array) $request->input('entry', []);

        // Kelompokkan perubahan per gateway pemilik: phone_number_id
        // penerima menentukan gateway (dan tenant) untuk tanda tangan
        // maupun atribusi. Tanpa kecocokan = ember global (null).
        $groups = [];
        foreach ($entries as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = (array) ($change['value'] ?? []);
                $phoneNumberId = (string) (($value['metadata'] ?? [])['phone_number_id'] ?? '');
                $groups[$phoneNumberId][] = $value;
            }
        }

        $accepted = false;
        foreach ($groups as $phoneNumberId => $values) {
            $gateway = $phoneNumberId === '' ? null : $this->gatewayByPhoneNumberId($phoneNumberId);
            $owner = $gateway ?? WaGateway::activeForTenant(null);

            // Keaslian POST per gateway: tanda tangan harus cocok
            // dengan App Secret gateway pemilik callback ini. Tanpa
            // secret / tidak cocok → kelompok ini ditolak seluruhnya
            // SEBELUM payload diproses (fail-closed).
            $secret = $owner?->credential('app_secret', '') ?? '';
            $expected = $secret === ''
                ? ''
                : 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
            $signature = (string) $request->header('X-Hub-Signature-256', '');
            if ($expected === '' || ! hash_equals($expected, $signature)) {
                continue;
            }
            $accepted = true;

            // Tanda tangan sah = callback berkredensial (bukan tamu):
            // cabut penanda tamu HTTP agar resolusi pelanggan pengirim
            // tidak gagal-tertutup oleh TenantScope (temuan 8).
            TenantContext::forgetGuestHttp();

            foreach ($values as $value) {
                foreach ((array) ($value['statuses'] ?? []) as $status) {
                    $this->applyStatus((array) $status);
                }
                foreach ((array) ($value['messages'] ?? []) as $incoming) {
                    $this->storeIncoming((array) $incoming, $owner);
                }
            }
        }

        // Tak satu pun kelompok lolos verifikasi = tolak persis
        // perilaku lama (403) agar pemalsuan tetap terdeteksi.
        return $accepted ? response('OK', 200) : response('Forbidden', 403);
    }

    /** Gateway yang kredensialnya memuat phone_number_id ini (unik per nomor Meta). */
    private function gatewayByPhoneNumberId(string $phoneNumberId): ?WaGateway
    {
        return WaGateway::all()->first(
            fn (WaGateway $g) => $g->credential('phone_number_id', '') === $phoneNumberId
        );
    }

    /** @param array<string, mixed> $status */
    private function applyStatus(array $status): void
    {
        $providerId = (string) ($status['id'] ?? '');
        $state = (string) ($status['status'] ?? '');
        if ($providerId === '' || $state !== 'delivered') {
            return;
        }
        WaMessage::where('provider_message_id', $providerId)
            ->where('status', 'sent')
            ->update(['status' => 'delivered']);
    }

    /** @param array<string, mixed> $incoming */
    private function storeIncoming(array $incoming, ?WaGateway $gateway): void
    {
        $providerId = (string) ($incoming['id'] ?? '');
        if ($providerId === '' || WaMessage::where('provider_message_id', $providerId)->exists()) {
            return; // Webhook Meta dapat mengirim ulang; jangan gandakan.
        }
        $phone = MessagingService::normalizePhone((string) ($incoming['from'] ?? ''));
        // Nomor tersimpan pelanggan berformat bebas (spasi/strip) —
        // pencocokan harus pada bentuk ternormalisasi kedua sisi.
        $tenantId = $gateway?->tenant_id;

        if ($tenantId !== null) {
            // Gateway milik tenant: pelanggan hanya dicari di dalam
            // tenant itu (deterministik — nomor yang sama di tenant
            // lain tidak relevan). Duplikat di dalam tenant yang sama
            // diambil yang pertama berdasarkan id.
            $customer = Customer::query()
                ->where('tenant_id', $tenantId)
                ->select(['id', 'phone'])
                ->get()
                ->filter(fn (Customer $c) => MessagingService::normalizePhone((string) $c->phone) === $phone)
                ->sortBy('id')
                ->first();
        } else {
            // Jalur global (mode mandiri / gateway platform): aturan
            // deterministik lama — pelanggan hanya dipetakan bila
            // nomor itu UNIK di seluruh instalasi; nomor ambigu tidak
            // ditebak dan pesan menjadi yatim (customer_id NULL).
            $matches = Customer::query()->select(['id', 'phone'])->get()
                ->filter(fn (Customer $c) => MessagingService::normalizePhone((string) $c->phone) === $phone);
            $customer = $matches->count() === 1 ? $matches->first() : null;
        }

        WaMessage::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customer?->id,
            'phone' => $phone,
            'template_key' => null,
            'body' => (string) ($incoming['text']['body'] ?? ''),
            'direction' => 'in',
            'status' => 'delivered',
            'trigger' => 'inbox',
            'sent_by' => null,
            'provider_message_id' => $providerId,
            'sent_at' => now(),
        ]);
    }
}
