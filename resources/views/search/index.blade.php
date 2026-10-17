<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.search_page.title') }} {{ __('ui.search_page.for') }} "{{ $q }}"</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if ($users->isEmpty() && $branches->isEmpty() && $products->isEmpty() && $customers->isEmpty() && $sales->isEmpty() && $purchaseRequests->isEmpty() && $purchaseOrders->isEmpty())
                <div class="rounded-lg bg-white shadow-soft">
                    <x-lentera.empty-state>{{ __('ui.search_page.empty', ['q' => $q]) }}</x-lentera.empty-state>
                </div>
            @endif

            @if ($users->isNotEmpty())
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('ui.search_page.users') }}</h2>
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    @foreach ($users as $user)
                        <a href="{{ route('users.show', $user) }}" class="flex items-center justify-between border-b border-gray-100 px-4 py-3 transition duration-150 last:border-0 hover:bg-lentera-50">
                            <span>
                                <span class="block font-medium text-gray-800">{{ $user->name }}</span>
                                <span class="block text-xs text-gray-400">{{ $user->username }}@if ($user->email) · {{ $user->email }}@endif</span>
                            </span>
                            <x-lentera.badge :active="$user->is_active" />
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($sales->isNotEmpty())
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('ui.search_page.sales') }}</h2>
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    @foreach ($sales as $sale)
                        <a href="{{ route('sales.show', $sale) }}" class="flex items-center justify-between border-b border-gray-100 px-4 py-3 transition duration-150 last:border-0 hover:bg-lentera-50">
                            <span>
                                <span class="block font-medium text-gray-800">{{ $sale->number }}</span>
                                <span class="block text-xs text-gray-400">{{ $sale->customer->name }} · {{ $sale->sale_date->format('d/m/Y') }}</span>
                            </span>
                            <span class="tnum text-sm text-gray-600">{{ \App\Support\Money::fromDecimal((string) $sale->total_amount)->format() }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($purchaseRequests->isNotEmpty())
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('ui.search_page.purchase_requests') }}</h2>
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    @foreach ($purchaseRequests as $pr)
                        <a href="{{ route('pr.show', $pr) }}" class="flex items-center justify-between border-b border-gray-100 px-4 py-3 transition duration-150 last:border-0 hover:bg-lentera-50">
                            <span>
                                <span class="block font-medium text-gray-800">{{ $pr->number }}</span>
                                <span class="block text-xs text-gray-400">{{ $pr->branch->name }} · {{ __('ui.purchasing.pr_statuses.'.$pr->status) }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($purchaseOrders->isNotEmpty())
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('ui.search_page.purchase_orders') }}</h2>
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    @foreach ($purchaseOrders as $po)
                        <a href="{{ route('po.show', $po) }}" class="flex items-center justify-between border-b border-gray-100 px-4 py-3 transition duration-150 last:border-0 hover:bg-lentera-50">
                            <span>
                                <span class="block font-medium text-gray-800">{{ $po->number }}</span>
                                <span class="block text-xs text-gray-400">{{ $po->supplier->name }} · {{ $po->branch->name }}</span>
                            </span>
                            <span class="tnum text-sm text-gray-600">{{ \App\Support\Money::fromDecimal((string) $po->total_amount)->format() }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($customers->isNotEmpty())
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('ui.search_page.customers') }}</h2>
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    @foreach ($customers as $customer)
                        <a href="{{ route('customers.show', $customer) }}" class="flex items-center justify-between border-b border-gray-100 px-4 py-3 transition duration-150 last:border-0 hover:bg-lentera-50">
                            <span>
                                <span class="block font-medium text-gray-800">{{ $customer->name }}</span>
                                <span class="block text-xs text-gray-400">{{ $customer->phone }}</span>
                            </span>
                            <x-lentera.badge :active="$customer->is_active" />
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($products->isNotEmpty())
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('ui.search_page.products') }}</h2>
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    @foreach ($products as $product)
                        <a href="{{ route('products.show', $product) }}" class="flex items-center justify-between border-b border-gray-100 px-4 py-3 transition duration-150 last:border-0 hover:bg-lentera-50">
                            <span>
                                <span class="block font-medium text-gray-800">{{ $product->name }}</span>
                                <span class="block text-xs text-gray-400">{{ $product->sku }} · {{ $product->typeLabel() }}</span>
                            </span>
                            <x-lentera.badge :active="$product->is_active" />
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($branches->isNotEmpty())
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('ui.search_page.branches') }}</h2>
                <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                    @foreach ($branches as $branch)
                        <a href="{{ route('branches.show', $branch) }}" class="flex items-center justify-between border-b border-gray-100 px-4 py-3 transition duration-150 last:border-0 hover:bg-lentera-50">
                            <span>
                                <span class="block font-medium text-gray-800">{{ $branch->name }}</span>
                                <span class="block text-xs text-gray-400">{{ $branch->phone ?: '—' }}</span>
                            </span>
                            <x-lentera.badge :active="$branch->is_active" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
