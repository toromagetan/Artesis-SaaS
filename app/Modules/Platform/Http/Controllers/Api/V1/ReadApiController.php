<?php

namespace App\Modules\Platform\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Master\Models\Branch;
use App\Modules\Membership\Services\PointsService;
use App\Modules\Platform\Models\ApiToken;
use App\Modules\Reporting\Services\ReportService;
use App\Modules\Sales\Models\Sale;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API v1 read-only (F4.6): hanya GET, di balik middleware token
 * bercakupan. Data tingkat tenant (token = kredensial integrasi
 * owner); filter ?cabang=<id> membatasi ke satu cabang.
 */
class ReadApiController extends Controller
{
    public function products(Request $request): JsonResponse
    {
        // LNT-004: harga modal hanya untuk token bercakupan products.cost.
        $token = $request->attributes->get('api_token');
        $withCost = $token instanceof ApiToken && $token->allows('products.cost');
        $products = Product::query()
            ->where('is_active', true)
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%")))
            ->orderBy('name')
            ->paginate(50);

        return response()->json([
            'data' => collect($products->items())->map(function (Product $p) use ($withCost) {
                $row = [
                    'id' => $p->id, 'sku' => $p->sku, 'barcode' => $p->barcode, 'type' => $p->type,
                    'name' => $p->name, 'brand' => $p->brand, 'sale_price' => (string) $p->sale_price,
                ];
                if ($withCost) {
                    $row['cost_price'] = (string) $p->cost_price;
                }

                return $row;
            })->all(),
            'meta' => ['current_page' => $products->currentPage(), 'last_page' => $products->lastPage(), 'total' => $products->total()],
        ]);
    }

    public function customers(Request $request): JsonResponse
    {
        // LNT-004: nomor HP hanya untuk token bercakupan customers.contact.
        $token = $request->attributes->get('api_token');
        $withContact = $token instanceof ApiToken && $token->allows('customers.contact');
        $points = app(PointsService::class);
        $customers = Customer::query()
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->orderBy('name')
            ->paginate(50);

        return response()->json([
            'data' => collect($customers->items())->map(function (Customer $c) use ($withContact, $points) {
                $row = [
                    'id' => $c->id, 'name' => $c->name,
                    'branch_id' => $c->branch_id, 'points_balance' => $points->balance($c),
                ];
                if ($withContact) {
                    $row['phone'] = $c->phone;
                }

                return $row;
            })->all(),
            'meta' => ['current_page' => $customers->currentPage(), 'last_page' => $customers->lastPage(), 'total' => $customers->total()],
        ]);
    }

    public function sales(Request $request): JsonResponse
    {
        $sales = Sale::query()
            ->with('items')
            ->when($request->query('cabang'), fn ($q, $branch) => $q->where('branch_id', (int) $branch))
            ->when($request->query('dari'), fn ($q, $from) => $q->where('sale_date', '>=', $from))
            ->when($request->query('sampai'), fn ($q, $to) => $q->where('sale_date', '<=', $to))
            ->orderByDesc('sale_date')->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'data' => collect($sales->items())->map(fn (Sale $s) => [
                'id' => $s->id, 'number' => $s->number, 'branch_id' => $s->branch_id,
                'customer_id' => $s->customer_id, 'sale_date' => $s->sale_date?->toDateString(),
                'status' => $s->status, 'total' => (string) $s->total_amount,
                'items' => $s->items->map(fn ($i) => [
                    'product_id' => $i->product_id, 'name' => $i->product_name_snapshot,
                    'qty' => $i->qty, 'line_total' => (string) $i->line_total,
                ])->all(),
            ])->all(),
            'meta' => ['current_page' => $sales->currentPage(), 'last_page' => $sales->lastPage(), 'total' => $sales->total()],
        ]);
    }

    public function stock(Request $request): JsonResponse
    {
        $branchIds = $request->query('cabang')
            ? [(int) $request->query('cabang')]
            : Branch::pluck('id')->map(fn ($id) => (int) $id)->all();
        $report = app(ReportService::class)->stockReport($branchIds);

        return response()->json([
            'data' => collect($report)->map(fn ($row) => [
                'product_id' => $row['product_id'],
                'name' => $row['product_name'],
                'branch_id' => $row['branch_id'],
                'qty' => $row['qty'],
                'value' => $row['value']->toDecimalString(),
            ])->values()->all(),
        ]);
    }

    public function reportsSummary(Request $request): JsonResponse
    {
        $from = (string) $request->query('dari', now()->startOfMonth()->toDateString());
        $to = (string) $request->query('sampai', now()->toDateString());
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) !== 1) {
            $from = now()->startOfMonth()->toDateString();
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) !== 1) {
            $to = now()->toDateString();
        }
        $branchIds = $request->query('cabang')
            ? [(int) $request->query('cabang')]
            : Branch::pluck('id')->map(fn ($id) => (int) $id)->all();
        $rows = app(ReportService::class)->salesSummary($from, $to, $branchIds);
        $total = Money::zero();
        foreach ($rows as $row) {
            $total = $total->add($row['net']);
        }

        return response()->json([
            'data' => collect($rows)->map(fn ($row) => [
                'branch_id' => $row['branch_id'],
                'transactions' => $row['transactions'],
                'net' => $row['net']->toDecimalString(),
            ])->all(),
            'meta' => ['from' => $from, 'to' => $to, 'net_total' => $total->toDecimalString()],
        ]);
    }
}
