<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.purchasing.pr_title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <form method="GET" class="flex gap-2">
                    <x-text-input name="q" :value="$q" :placeholder="__('ui.purchasing.search_placeholder')" class="w-64" />
                    <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">{{ __('ui.purchasing.status') }}</option>
                        @foreach (\App\Modules\Purchasing\Models\PurchaseRequest::STATUSES as $s)
                            <option value="{{ $s }}" @selected($status === $s)>{{ __('ui.purchasing.pr_statuses.'.$s) }}</option>
                        @endforeach
                    </select>
                    <x-secondary-button type="submit">{{ __('ui.search') }}</x-secondary-button>
                </form>
                <x-lentera.dropdown-aksi>
                    @can('pr.create')
                        <x-lentera.menu-item :href="route('pr.create')">{{ __('ui.purchasing.pr_create') }}</x-lentera.menu-item>
                    @endcan
                </x-lentera.dropdown-aksi>
            </div>

            <form method="POST" action="{{ route('pr.convert-multiple') }}">
                @csrf
                <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-500">
                                <th class="px-4 py-3 font-medium"></th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.number') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.date') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.requester') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.branch') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.items') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.purchasing.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($requests as $pr)
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="px-4 py-3">
                                        @if ($pr->status === 'approved')
                                            <input type="checkbox" name="pr_ids[]" value="{{ $pr->id }}" class="rounded border-gray-300 text-lentera-700 focus:ring-lentera-500">
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-medium"><a href="{{ route('pr.show', $pr) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $pr->number }}</a></td>
                                    <td class="px-4 py-3">{{ $pr->created_at->format('d/m/Y') }}</td>
                                    <td class="px-4 py-3">{{ $pr->requester->name }}</td>
                                    <td class="px-4 py-3">{{ $pr->branch->name }}</td>
                                    <td class="px-4 py-3 tnum">{{ $pr->items_count }}</td>
                                    <td class="px-4 py-3"><x-lentera.badge>{{ __('ui.purchasing.pr_statuses.'.$pr->status) }}</x-lentera.badge></td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><x-lentera.empty-state>{{ __('ui.purchasing.empty_pr') }}</x-lentera.empty-state></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @can('pr.approve')
                    <div class="mt-4 flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-soft">
                        <div>
                            <x-input-label for="merge_supplier" :value="__('ui.purchasing.supplier')" />
                            <select id="merge_supplier" name="supplier_id" class="mt-1 block w-64 rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                @foreach (\App\Modules\Master\Models\Supplier::where('is_active', true)->orderBy('name')->get() as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-secondary-button type="submit">{{ __('ui.purchasing.convert_multi') }}</x-secondary-button>
                        <p class="text-xs text-gray-400">{{ __('ui.purchasing.convert_multi') }}: centang PR berstatus Approved (supplier & cabang tujuan sama).</p>
                    </div>
                @endcan
            </form>

            <div class="mt-4">{{ $requests->links() }}</div>
        </div>
    </div>
</x-app-layout>
