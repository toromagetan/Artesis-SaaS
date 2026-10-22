<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.suppliers.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex justify-end">
                <x-lentera.dropdown-aksi>
                    <x-lentera.menu-item :href="route('suppliers.create')">{{ __('ui.suppliers.create') }}</x-lentera.menu-item>
                </x-lentera.dropdown-aksi>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.suppliers.name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.suppliers.contact') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.suppliers.address') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('ui.suppliers.po_count') }}</th>
                            <th class="px-4 py-3 font-medium text-right">{{ __('ui.suppliers.outstanding') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.suppliers.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($suppliers as $supplier)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    <a href="{{ route('suppliers.show', $supplier) }}" class="text-lentera-800 underline decoration-lentera-300 underline-offset-2 hover:text-lentera-950">{{ $supplier->name }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ $supplier->contact_person ?? '—' }}
                                    @if ($supplier->phone)<div class="text-xs text-gray-400">{{ $supplier->phone }}</div>@endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ \Illuminate\Support\Str::limit($supplier->address ?? '—', 40) }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $supplier->po_count }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ $supplier->outstanding->format() }}</td>
                                <td class="px-4 py-3"><x-lentera.badge :active="$supplier->is_active" /></td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.row-actions>
                                        <x-lentera.menu-item :href="route('suppliers.show', $supplier)">{{ __('ui.suppliers.detail') }}</x-lentera.menu-item>
                                        <x-lentera.menu-item :href="route('suppliers.edit', $supplier)">{{ __('ui.edit') }}</x-lentera.menu-item>
                                        <div class="my-1 border-t border-gray-100"></div>
                                        <form method="POST" action="{{ route('suppliers.toggle-active', $supplier) }}">
                                            @csrf
                                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm transition duration-150 hover:bg-lentera-50 {{ $supplier->is_active ? 'text-red-600' : 'text-lentera-700' }}">{{ $supplier->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                                        </form>
                                    </x-lentera.row-actions>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-lentera.empty-state>{{ __('ui.suppliers.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $suppliers->links() }}</div>
        </div>
    </div>
</x-app-layout>
