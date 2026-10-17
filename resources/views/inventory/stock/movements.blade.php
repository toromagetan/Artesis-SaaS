<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.stock.movements') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center justify-between">
                <form method="GET" class="flex gap-2">
                    <x-text-input name="q" :value="$q" :placeholder="__('ui.stock.search_placeholder')" class="w-64" />
                    <select name="cabang" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">{{ __('ui.stock.branch') }}</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $branchId === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <select name="alasan" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">{{ __('ui.stock.reason') }}</option>
                        @foreach (\App\Modules\Inventory\Models\StockMovement::REASONS as $r)
                            <option value="{{ $r }}" @selected($reason === $r)>{{ __('ui.stock.reasons.'.$r) }}</option>
                        @endforeach
                    </select>
                    <x-secondary-button type="submit">{{ __('ui.search') }}</x-secondary-button>
                </form>
                <a href="{{ route('stock.index') }}" class="text-sm text-lentera-800 underline">{{ __('ui.stock.title') }}</a>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.stock.date') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.stock.product') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.stock.branch') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.stock.reason') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.stock.qty') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.stock.reference') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.stock.balance_after') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.stock.by') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movements as $movement)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $movement->product->name }}</td>
                                <td class="px-4 py-3">{{ $movement->branch->name }}</td>
                                <td class="px-4 py-3">{{ __('ui.stock.reasons.'.$movement->reason) }}</td>
                                <td class="px-4 py-3 tnum font-medium {{ $movement->qty < 0 ? 'text-red-600' : 'text-lentera-700' }}">{{ $movement->qty > 0 ? '+' : '' }}{{ $movement->qty }}</td>
                                <td class="px-4 py-3">{{ $movement->reference_type }} #{{ $movement->reference_id }}</td>
                                <td class="px-4 py-3 tnum">{{ $movement->balance_after }}</td>
                                <td class="px-4 py-3">{{ $movement->creator->name }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><x-lentera.empty-state>{{ __('ui.stock.movements_empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $movements->links() }}</div>
        </div>
    </div>
</x-app-layout>
