<?php

namespace App\Modules\Platform\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Reporting\Services\AnalyticsService;
use App\Modules\Reporting\Services\ReportService;
use App\Support\Money;
use RuntimeException;

/**
 * Asisten AI bisnis (F5.1) — owner-only, read-only. Pertanyaan
 * diterjemahkan AiTranslator menjadi intent daftar putih; angka
 * HANYA dari service Lentera di bawah pagar BranchContext aktor;
 * setiap jawaban menyertakan dasar data (periode, cabang, sumber).
 */
class AiAssistantService
{
    public function __construct(
        private readonly AiTranslator $translator,
        private readonly ReportService $reports,
        private readonly AnalyticsService $analytics,
        private readonly InventoryService $inventory,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{status: string, text: string, basis: array<string, mixed>}
     */
    public function ask(User $actor, string $question): array
    {
        if (! $actor->hasRole('Owner')) {
            abort(403);
        }

        try {
            $translated = $this->translator->translate($question);
        } catch (RuntimeException $e) {
            return ['status' => $e->getMessage(), 'text' => '', 'basis' => []];
        }

        $intent = $translated['intent'];
        $params = $translated['params'];
        $context = BranchContext::for($actor);
        $branchIds = $context->hasGlobalAccess()
            ? Branch::pluck('id')->map(fn ($id) => (int) $id)->all()
            : $context->managedBranchIds();
        if (! empty($params['branch'])) {
            $named = Branch::where('name', 'like', '%'.$params['branch'].'%')->pluck('id')->map(fn ($id) => (int) $id)->all();
            $branchIds = array_values(array_intersect($branchIds, $named));
        }
        [$from, $to] = $this->period($params['period'] ?? 'current_month');
        $branchNames = Branch::whereIn('id', $branchIds)->pluck('name')->all();
        $basis = ['intent' => $intent, 'from' => $from, 'to' => $to, 'branches' => $branchNames];

        $result = match ($intent) {
            'sales_total' => $this->salesTotal($branchIds, $from, $to, $basis),
            'stock_qty' => $this->stockQty($branchIds, $params, $basis),
            'top_product' => $this->topProduct($branchIds, $from, $to, $basis),
            'payables_total' => $this->payablesTotal($branchIds, $basis),
            default => ['status' => 'unknown', 'text' => '', 'basis' => $basis],
        };

        $this->audit->log(action: 'ai.ask', subjectType: 'ai_question', subjectId: null,
            newValues: ['intent' => $intent, 'status' => $result['status']], actor: $actor);

        return $result;
    }

    /** @return array{0: string, 1: string} */
    private function period(string $period): array
    {
        if ($period === 'today') {
            return [now()->toDateString(), now()->toDateString()];
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $m) === 1) {
            $start = $m[1].'-'.$m[2].'-01';

            return [$start, date('Y-m-t', strtotime($start))];
        }

        return [now()->startOfMonth()->toDateString(), now()->toDateString()];
    }

    private function salesTotal(array $branchIds, string $from, string $to, array $basis): array
    {
        $total = Money::zero();
        foreach ($this->reports->salesSummary($from, $to, $branchIds) as $row) {
            $total = $total->add($row['total']);
        }

        return ['status' => 'ok', 'text' => $total->format(), 'basis' => $basis + ['source' => 'ReportService::salesSummary']];
    }

    private function stockQty(array $branchIds, array $params, array $basis): array
    {
        $query = (string) ($params['product'] ?? '');
        $product = $query === '' ? null : Product::all()->first(
            fn (Product $p) => str_contains(mb_strtolower($p->name), mb_strtolower($query))
                || str_contains(mb_strtolower($query), mb_strtolower($p->name))
        );
        if ($product === null) {
            return ['status' => 'unknown', 'text' => '', 'basis' => $basis];
        }
        $qty = 0;
        foreach ($branchIds as $branchId) {
            $qty += $this->inventory->balancesForBranch($branchId)[$product->id] ?? 0;
        }

        return ['status' => 'ok', 'text' => $product->name.': '.$qty, 'basis' => $basis + ['product' => $product->name, 'source' => 'InventoryService']];
    }

    private function topProduct(array $branchIds, string $from, string $to, array $basis): array
    {
        $top = $this->analytics->salesAnalytics($branchIds, $from, $to)['top_products'][0] ?? null;
        if ($top === null) {
            return ['status' => 'ok', 'text' => '-', 'basis' => $basis + ['source' => 'AnalyticsService::salesAnalytics']];
        }

        return ['status' => 'ok', 'text' => $top['name'], 'basis' => $basis + ['source' => 'AnalyticsService::salesAnalytics']];
    }

    private function payablesTotal(array $branchIds, array $basis): array
    {
        $payables = $this->reports->supplierPayables($branchIds);

        return ['status' => 'ok', 'text' => $payables['grand_total']->format(), 'basis' => $basis + ['source' => 'ReportService::supplierPayables']];
    }
}
