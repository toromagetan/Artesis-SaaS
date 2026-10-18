<?php

namespace App\Modules\Membership\Services;

use App\Modules\CRM\Models\Customer;
use App\Modules\Lab\Services\ProcurementStatusService;
use App\Modules\Membership\Models\MemberPortalToken;
use App\Modules\Membership\Models\PointsLedger;
use App\Modules\Sales\Models\Sale;
use Illuminate\Support\Str;

/**
 * Portal member publik ber-token (F3.7, Blueprint D.8).
 *
 * Token 48 karakter acak; yang disimpan hanya hash sha256-nya.
 * Menerbitkan token baru mencabut semua token lama pelanggan.
 * Portal menampilkan HANYA data pemilik token: saldo & riwayat
 * poin, level, riwayat pembelian, dan tahap orderan per transaksi
 * (status turunan ProcurementStatusService).
 */
class MemberPortalService
{
    public function __construct(
        private readonly PointsService $points,
        private readonly ProcurementStatusService $stages,
    ) {}

    /** Terbitkan token baru; mengembalikan token mentah (sekali lihat). */
    public function issueToken(Customer $customer): string
    {
        MemberPortalToken::where('customer_id', $customer->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $token = Str::random(48);
        MemberPortalToken::create([
            'customer_id' => $customer->id,
            'token_hash' => hash('sha256', $token),
        ]);

        return $token;
    }

    /** Cabut semua token aktif pelanggan. */
    public function revoke(Customer $customer): void
    {
        MemberPortalToken::where('customer_id', $customer->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /** Resolusi token mentah -> pelanggan, atau null bila tidak sah. */
    public function resolve(string $token): ?Customer
    {
        $row = MemberPortalToken::where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->first();
        if ($row === null) {
            return null;
        }
        $row->forceFill(['last_used_at' => now()])->save();

        return $row->customer;
    }

    /**
     * Data portal untuk satu pelanggan — tidak pernah memuat data
     * pelanggan lain.
     *
     * @return array<string, mixed>
     */
    public function dataFor(Customer $customer): array
    {
        $sales = Sale::where('customer_id', $customer->id)
            ->orderByDesc('sale_date')->orderByDesc('id')
            ->limit(20)->get();

        $orders = $sales->map(fn (Sale $sale) => [
            'sale' => $sale,
            'stage' => $sale->isVoid() ? 'void' : $this->stages->stageFor($sale),
        ])->all();

        return [
            'customer' => $customer,
            'level' => $this->points->currentLevel($customer),
            'balance' => $this->points->balance($customer),
            'pointValue' => $this->points->pointValue(),
            'orders' => $orders,
            'ledger' => PointsLedger::where('customer_id', $customer->id)
                ->orderByDesc('id')->limit(20)->get(),
        ];
    }
}
