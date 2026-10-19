<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Services\CustomerService;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Insurance;
use App\Modules\Sales\Services\SalesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pelanggan (Blueprint D.4 + PRD Detail 3.1/3.2 + ADR-012).
 * MASTER GLOBAL: daftar & detail berlaku semua cabang; pagarnya
 * izin customers.* (middleware rute). Filter `cabang` menyaring
 * berdasarkan cabang pendaftaran (atribut asal), bukan pagar.
 */
class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $branchId = $request->query('cabang');

        $customers = Customer::with('branch')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%");
                });
            })
            ->when($branchId, fn ($query) => $query->where('branch_id', (int) $branchId))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // Pelanggan global: daftar cabang untuk filter asal memuat
        // semua cabang (master tenant), untuk semua pengguna berizin.
        $branches = Branch::orderBy('name')->get();

        return view('crm.customers.index', compact('customers', 'q', 'branchId', 'branches'));
    }

    public function show(Customer $customer): View
    {
        $customer->load(['branch', 'insurance', 'prescriptions.examiner']);
        $outstanding = app(SalesService::class)->outstandingReceivable($customer->id);
        // Kredit toko terpagar cabang (BranchScoped pada model):
        // aktor hanya melihat voucher dari cabang yang ia kelola.
        $credits = $customer->credits()->with(['saleReturn', 'sale'])->get();

        return view('crm.customers.show', compact('customer', 'outstanding', 'credits'));
    }

    public function create(): View
    {
        return view('crm.customers.create', [
            'branches' => $this->selectableBranches(),
            'insurances' => Insurance::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string'],
            'birth_date' => ['nullable', 'date'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')],
            'insurance_id' => ['nullable', Rule::exists('insurances', 'id')],
        ]);

        $result = $this->service->create($request->user(), $validated);

        $redirect = redirect()->route('customers.show', $result['customer'])
            ->with('status', __('ui.customers.created'));
        if ($result['phone_shared']) {
            $redirect->with('warning', __('ui.customers.phone_shared_warning'));
        }

        return $redirect;
    }

    public function edit(Customer $customer): View
    {
        return view('crm.customers.edit', [
            'customer' => $customer,
            'insurances' => Insurance::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string'],
            'birth_date' => ['nullable', 'date'],
            'insurance_id' => ['nullable', Rule::exists('insurances', 'id')],
        ]);

        $result = $this->service->update($request->user(), $customer, $validated);

        $redirect = redirect()->route('customers.show', $customer)
            ->with('status', __('ui.customers.updated'));
        if ($result['phone_shared']) {
            $redirect->with('warning', __('ui.customers.phone_shared_warning'));
        }

        return $redirect;
    }

    public function toggleActive(Request $request, Customer $customer): RedirectResponse
    {
        $this->service->setActive($request->user(), $customer, ! $customer->is_active);

        return back()->with('status', $customer->is_active ? __('ui.customers.activated') : __('ui.customers.deactivated'));
    }

    private function selectableBranches()
    {
        $context = BranchContext::current();

        return $context->hasGlobalAccess()
            ? Branch::where('is_active', true)->orderBy('name')->get()
            : Branch::whereIn('id', $context->managedBranchIds())->where('is_active', true)->orderBy('name')->get();
    }
}
