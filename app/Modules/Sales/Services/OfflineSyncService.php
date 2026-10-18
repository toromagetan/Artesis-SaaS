<?php

namespace App\Modules\Sales\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Sales\Models\OfflineDevice;
use App\Modules\Sales\Models\OfflineException;
use App\Modules\Sales\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Mesin sinkronisasi kasir offline (F5.3). Setiap niat transaksi
 * dari perangkat diproses berurutan waktu offline:
 * - client_uuid sudah tercatat -> duplikat (idempoten).
 * - pembayaran non-tunai -> antrean pengecualian (tidak dapat
 *   dikonfirmasi tanpa jaringan).
 * - SalesService menolak (mis. stok tipe ketat habis) -> antrean
 *   pengecualian dengan alasan, untuk resolusi staf.
 * - lolos -> penjualan kanonis bernomor server; nomor struk lokal
 *   perangkat tersimpan di catatan penjualan.
 */
class OfflineSyncService
{
    public function __construct(private readonly SalesService $sales) {}

    /**
     * @param  array<int, array<string, mixed>>  $intents
     * @return array<int, array<string, mixed>> hasil per niat
     */
    public function sync(OfflineDevice $device, array $intents): array
    {
        usort($intents, fn ($a, $b) => strcmp((string) ($a['offline_at'] ?? ''), (string) ($b['offline_at'] ?? '')));
        $results = [];
        foreach ($intents as $intent) {
            $results[] = $this->syncOne($device, $intent);
        }
        $device->update(['last_sync_at' => now()]);

        return $results;
    }

    /** @param array<string, mixed> $intent */
    private function syncOne(OfflineDevice $device, array $intent): array
    {
        $uuid = (string) ($intent['client_uuid'] ?? '');
        if ($uuid === '') {
            return ['client_uuid' => null, 'status' => 'exception', 'reason' => 'missing_uuid'];
        }

        $existing = Sale::where('client_uuid', $uuid)->first();
        if ($existing !== null) {
            return ['client_uuid' => $uuid, 'status' => 'duplicate', 'sale_id' => $existing->id, 'number' => $existing->number];
        }
        $priorException = OfflineException::where('offline_device_id', $device->id)
            ->where('client_uuid', $uuid)->where('status', 'pending')->first();
        if ($priorException !== null) {
            return ['client_uuid' => $uuid, 'status' => 'exception', 'reason' => $priorException->reason, 'exception_id' => $priorException->id];
        }

        $payments = $intent['payments'] ?? [];
        $hasNonCash = collect($payments)->contains(fn ($p) => ($p['method'] ?? 'cash') !== 'cash');
        if ($hasNonCash) {
            $exception = $this->recordException($device, $intent, 'non_cash_offline');

            return ['client_uuid' => $uuid, 'status' => 'exception', 'reason' => 'non_cash_offline', 'exception_id' => $exception->id];
        }

        try {
            $sale = $this->createFromIntent($device, $intent);

            return ['client_uuid' => $uuid, 'status' => 'synced', 'sale_id' => $sale->id, 'number' => $sale->number];
        } catch (ValidationException $e) {
            $exception = $this->recordException($device, $intent, 'rejected', $e->errors());

            return ['client_uuid' => $uuid, 'status' => 'exception', 'reason' => 'rejected', 'exception_id' => $exception->id];
        }
    }

    /** @param array<string, mixed> $intent */
    public function createFromIntent(OfflineDevice $device, array $intent): Sale
    {
        $actor = User::findOrFail($device->user_id);

        return $this->sales->createSale($actor, [
            'customer_id' => $intent['customer_id'],
            'sale_date' => substr((string) ($intent['offline_at'] ?? now()->toDateTimeString()), 0, 10),
            'tax_mode' => $intent['tax_mode'] ?? 'exclusive',
            'branch_id' => $device->branch_id,
            'client_uuid' => (string) $intent['client_uuid'],
            'notes' => 'offline:'.$device->name.'/'.($intent['local_no'] ?? '-'),
        ], $intent['items'] ?? [], $intent['payments'] ?? []);
    }

    /** @param array<string, mixed> $intent */
    private function recordException(OfflineDevice $device, array $intent, string $reason, ?array $errors = null): OfflineException
    {
        $payload = $intent;
        if ($errors !== null) {
            $payload['_errors'] = $errors;
        }

        return OfflineException::create([
            'offline_device_id' => $device->id,
            'branch_id' => $device->branch_id,
            'client_uuid' => (string) $intent['client_uuid'],
            'local_no' => $intent['local_no'] ?? null,
            'payload' => $payload,
            'reason' => $reason,
            'status' => 'pending',
        ]);
    }

    /**
     * Resolusi: coba lagi niat dari antrean (mis. stok sudah datang).
     * Mengembalikan penjualan bila berhasil; pengecualian tetap
     * pending bila masih ditolak.
     */
    public function retry(User $actor, OfflineException $exception): ?Sale
    {
        $device = OfflineDevice::findOrFail($exception->offline_device_id);
        try {
            $sale = DB::transaction(function () use ($device, $exception) {
                $created = $this->createFromIntent($device, $exception->payload);
                $exception->update([
                    'status' => 'resolved', 'resolution_note' => 'retry: '.$created->number,
                ]);

                return $created;
            });
            $exception->update(['resolved_by' => $actor->id, 'resolved_at' => now()]);

            return $sale;
        } catch (ValidationException) {
            return null;
        }
    }

    public function discard(User $actor, OfflineException $exception, string $note): void
    {
        $exception->update([
            'status' => 'discarded', 'resolution_note' => $note,
            'resolved_by' => $actor->id, 'resolved_at' => now(),
        ]);
    }
}
