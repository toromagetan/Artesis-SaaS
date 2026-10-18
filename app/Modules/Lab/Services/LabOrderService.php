<?php

namespace App\Modules\Lab\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Lab\Events\LabOrderReceived;
use App\Modules\Lab\Models\LabOrder;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Sales\Models\Sale;
use App\Support\Money;
use App\Support\Numbering;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Order lab (F2.3, Blueprint B.6/D + E.3 + PRD Detail 5.3).
 *
 * Status HANYA maju: waiting → ordered (sent_at terisi) → received
 * (received_at terisi; lab_cost final diposting ke HPP oleh listener
 * akuntansi lewat LabOrderReceived). Semua mutasi di bawah izin
 * lab.order.manage dan lockForUpdate.
 */
class LabOrderService
{
    private const NEXT = [
        'waiting' => 'ordered',
        'ordered' => 'received',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    private function assertManager(User $actor): void
    {
        if (! $actor->hasPermission('lab.order.manage')) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    public function create(User $actor, Sale $sale, string $labName, string $labCost = '0'): LabOrder
    {
        $this->assertManager($actor);

        $cost = Money::fromDecimal($labCost);
        if ($cost->isNegative()) {
            throw ValidationException::withMessages(['lab_cost' => __('ui.lab_orders.cost_invalid')]);
        }

        return DB::transaction(function () use ($actor, $sale, $labName, $cost) {
            // Status penjualan dibaca ulang segar dari DB (instans pemanggil
            // bisa basi — pola baca-ulang yang sama dengan mutasi lain).
            $fresh = Sale::withoutGlobalScopes()->lockForUpdate()->findOrFail($sale->id);
            if ($fresh->status !== 'active') {
                throw ValidationException::withMessages(['sale' => __('ui.lab_orders.sale_inactive')]);
            }

            $order = LabOrder::create([
                'number' => Numbering::next('LO', now()),
                'sale_id' => $fresh->id,
                'lab_name' => $labName,
                'status' => 'waiting',
                'lab_cost' => $cost->toDecimalString(),
                'created_by' => $actor->id,
            ]);

            $this->audit->log(
                action: 'lab_order.create',
                subjectType: 'lab_order',
                subjectId: $order->id,
                newValues: ['number' => $order->number, 'lab_name' => $labName, 'lab_cost' => $cost->toDecimalString()],
                branchId: (int) $fresh->branch_id,
                actor: $actor,
            );

            return $order;
        });
    }

    public function transition(User $actor, LabOrder $order, string $to, ?string $labCost = null): LabOrder
    {
        $this->assertManager($actor);

        return DB::transaction(function () use ($actor, $order, $to, $labCost) {
            $locked = LabOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            $expected = self::NEXT[$locked->status] ?? null;
            if ($expected === null || $to !== $expected) {
                throw ValidationException::withMessages([
                    'status' => __('ui.lab_orders.transition_invalid', ['from' => $locked->status, 'to' => $to]),
                ]);
            }

            if ($to === 'ordered') {
                $locked->sent_at = now();
            }
            if ($to === 'received') {
                $locked->received_at = now();
                if ($labCost !== null) {
                    $cost = Money::fromDecimal($labCost);
                    if ($cost->isNegative()) {
                        throw ValidationException::withMessages(['lab_cost' => __('ui.lab_orders.cost_invalid')]);
                    }
                    $locked->lab_cost = $cost->toDecimalString();
                }
            }
            $locked->status = $to;
            $locked->save();

            $sale = $locked->sale()->withoutGlobalScopes()->first();
            $this->audit->log(
                action: 'lab_order.transition',
                subjectType: 'lab_order',
                subjectId: $locked->id,
                newValues: ['status' => $to, 'lab_cost' => (string) $locked->lab_cost],
                branchId: $sale !== null ? (int) $sale->branch_id : null,
                actor: $actor,
            );

            if ($to === 'received') {
                LabOrderReceived::dispatch($locked);
            }

            return $locked;
        });
    }
}
