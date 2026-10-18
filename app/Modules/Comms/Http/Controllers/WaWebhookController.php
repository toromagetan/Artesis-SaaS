<?php

namespace App\Modules\Comms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\Comms\Services\GatewayFactory;
use App\Modules\Comms\Services\MessagingService;
use App\Modules\CRM\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Webhook Meta WhatsApp (F3.4; inbox dibaca F3.8).
 * GET  = jabat tangan verifikasi (hub.verify_token vs kredensial).
 * POST = pembaruan status terkirim (delivered) + tangkap pesan masuk
 *        sebagai baris direction 'in' agar tidak ada pesan hilang.
 * Endpoint publik tanpa sesi; keaslian dijaga verify_token (GET) dan
 * pencocokan phone_number_id + provider_message_id (POST).
 */
class WaWebhookController extends Controller
{
    public function verify(Request $request, GatewayFactory $factory): Response
    {
        $gateway = $factory->activeGateway();
        $expected = $gateway?->credential('verify_token', '');
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));

        if ($request->query('hub_mode') === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            return response((string) $request->query('hub_challenge', $request->query('hub.challenge', '')), 200);
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request): Response
    {
        $entries = (array) $request->input('entry', []);
        foreach ($entries as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = (array) ($change['value'] ?? []);

                foreach ((array) ($value['statuses'] ?? []) as $status) {
                    $this->applyStatus((array) $status);
                }
                foreach ((array) ($value['messages'] ?? []) as $incoming) {
                    $this->storeIncoming((array) $incoming);
                }
            }
        }

        return response('OK', 200);
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
    private function storeIncoming(array $incoming): void
    {
        $providerId = (string) ($incoming['id'] ?? '');
        if ($providerId === '' || WaMessage::where('provider_message_id', $providerId)->exists()) {
            return; // Webhook Meta dapat mengirim ulang; jangan gandakan.
        }
        $phone = MessagingService::normalizePhone((string) ($incoming['from'] ?? ''));
        // Nomor tersimpan pelanggan berformat bebas (spasi/strip) —
        // pencocokan harus pada bentuk ternormalisasi kedua sisi.
        $customer = Customer::query()->select(['id', 'phone'])->get()
            ->first(fn (Customer $c) => MessagingService::normalizePhone((string) $c->phone) === $phone);

        WaMessage::create([
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
