<?php

namespace App\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Master\Models\Branch;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Modules\Sales\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pencarian global (Blueprint F.2 butir 9): satu endpoint, hasil
 * dikelompokkan per jenis, terpagar IZIN dan CABANG di server sebelum
 * hasil dikembalikan. Kelompok pelanggan/produk/transaksi/PR-PO
 * bergabung di sini saat modulnya tiba.
 */
class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $user = $request->user();
        $context = BranchContext::for($user);

        $users = collect();
        $branches = collect();
        $products = collect();
        $customers = collect();
        $sales = collect();
        $purchaseRequests = collect();
        $purchaseOrders = collect();

        if ($q !== '') {
            if ($user->hasPermission('users.manage')) {
                $users = User::query()
                    ->where(function ($query) use ($q) {
                        $query->where('name', 'like', "%{$q}%")
                            ->orWhere('username', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%");
                    })
                    ->when(! $context->hasGlobalAccess(), function ($query) use ($context) {
                        // Pagar cabang: hanya pengguna yang berbagi cabang
                        // terkelola dengan pencari.
                        $query->whereHas('branches', fn ($b) => $b->whereIn('branches.id', $context->managedBranchIds()));
                    })
                    ->orderBy('name')
                    ->limit(10)
                    ->get();
            }

            if ($user->hasPermission('sales.view')) {
                // Penjualan terpagar cabang lewat BranchScope pada model.
                $sales = Sale::with('customer')
                    ->where(function ($query) use ($q) {
                        $query->where('number', 'like', "%{$q}%")
                            ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%"));
                    })
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get();
            }

            if ($user->hasPermission('pr.view')) {
                // PR terpagar cabang lewat BranchScope pada model.
                $purchaseRequests = PurchaseRequest::with('branch')
                    ->where('number', 'like', "%{$q}%")
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get();
            }

            if ($user->hasPermission('po.view')) {
                $purchaseOrders = PurchaseOrder::with(['supplier', 'branch'])
                    ->where('number', 'like', "%{$q}%")
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get();
            }

            if ($user->hasPermission('customers.view')) {
                // Pelanggan terpagar cabang lewat BranchScope pada model.
                $customers = Customer::query()
                    ->where(function ($query) use ($q) {
                        $query->where('name', 'like', "%{$q}%")
                            ->orWhere('phone', 'like', "%{$q}%");
                    })
                    ->orderBy('name')
                    ->limit(10)
                    ->get();
            }

            if ($user->hasPermission('products.view')) {
                // Produk adalah data master level tenant: pagarnya izin.
                $products = Product::query()
                    ->where(function ($query) use ($q) {
                        $query->where('name', 'like', "%{$q}%")
                            ->orWhere('sku', 'like', "%{$q}%")
                            ->orWhere('brand', 'like', "%{$q}%");
                    })
                    ->orderBy('name')
                    ->limit(10)
                    ->get();
            }

            if ($user->hasPermission('branches.manage')) {
                $branches = Branch::query()
                    ->where('name', 'like', "%{$q}%")
                    ->when(! $context->hasGlobalAccess(), fn ($query) => $query->whereIn('id', $context->managedBranchIds()))
                    ->orderBy('name')
                    ->limit(10)
                    ->get();
            }
        }

        return view('search.index', compact('q', 'users', 'branches', 'products', 'customers', 'sales', 'purchaseRequests', 'purchaseOrders'));
    }
}
