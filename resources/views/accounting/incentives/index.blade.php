<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.incentives.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <p class="rounded-md bg-lentera-100 px-4 py-2 text-sm text-lentera-900" role="status">{{ session('status') }}</p>
            @endif
            @if ($errors->any())
                <ul class="rounded-md bg-red-50 px-4 py-2 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            @endif

            <section class="rounded-lg bg-white p-6 shadow-soft">
                <h2 class="mb-3 font-semibold text-gray-800">{{ __('ui.incentives.generate_title') }}</h2>
                <form method="POST" action="{{ route('incentives.generate') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <label class="text-sm">
                        <span class="mb-1 block text-gray-500">{{ __('ui.incentives.branch') }}</span>
                        <select name="branch_id" class="rounded-md border-gray-300 text-sm">
                            @foreach ($branches as $b)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-gray-500">{{ __('ui.incentives.period_start') }}</span>
                        <input type="date" name="period_start" required class="rounded-md border-gray-300 text-sm">
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-gray-500">{{ __('ui.incentives.period_end') }}</span>
                        <input type="date" name="period_end" required class="rounded-md border-gray-300 text-sm">
                    </label>
                    <button type="submit" class="rounded-md bg-lentera-700 px-4 py-2 text-sm font-medium text-white transition duration-150 hover:bg-lentera-800">{{ __('ui.incentives.generate') }}</button>
                </form>
            </section>

            <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                <h2 class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">{{ __('ui.incentives.periods') }}</h2>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.incentives.period') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.incentives.recipient') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.incentives.branch') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.incentives.transaction_total') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('ui.incentives.total') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.incentives.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($periods as $period)
                            <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('incentives.show', $period) }}" class="font-medium text-lentera-800 underline">{{ $period->period_start->format('d/m/Y') }} – {{ $period->period_end->format('d/m/Y') }}</a>
                                </td>
                                <td class="px-4 py-3">{{ $period->recipient->name }}</td>
                                <td class="px-4 py-3">{{ $period->branch->name }}</td>
                                <td class="px-4 py-3 text-right tnum">{{ \App\Support\Money::fromDecimal((string) $period->transaction_total)->format() }}</td>
                                <td class="px-4 py-3 text-right font-medium tnum">{{ \App\Support\Money::fromDecimal((string) $period->incentive_total)->format() }}</td>
                                <td class="px-4 py-3">{{ __('ui.incentives.status_'.$period->status) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-lentera.empty-state>{{ __('ui.incentives.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="border-t border-gray-50 px-4 py-3">{{ $periods->links() }}</div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow-soft">
                <h2 class="mb-3 font-semibold text-gray-800">{{ __('ui.incentives.rules') }}</h2>
                <form method="POST" action="{{ route('incentives.rules.store') }}" class="mb-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <label class="text-sm">
                        <span class="mb-1 block text-gray-500">{{ __('ui.incentives.rule_name') }}</span>
                        <input type="text" name="name" required class="rounded-md border-gray-300 text-sm">
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-gray-500">{{ __('ui.incentives.role') }}</span>
                        <select name="recipient_role" class="rounded-md border-gray-300 text-sm">
                            @foreach ($roles as $role)
                                <option value="{{ $role }}">{{ $role }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-gray-500">{{ __('ui.incentives.product') }}</span>
                        <select name="product_id" class="rounded-md border-gray-300 text-sm">
                            <option value="">{{ __('ui.incentives.product_all') }}</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}">{{ $p->sku }} — {{ $p->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-gray-500">{{ __('ui.incentives.calc') }}</span>
                        <select name="calculation_type" class="rounded-md border-gray-300 text-sm">
                            <option value="percent">{{ __('ui.incentives.percent') }}</option>
                            <option value="flat">{{ __('ui.incentives.flat') }}</option>
                        </select>
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-gray-500">{{ __('ui.incentives.value') }}</span>
                        <input type="number" name="value" min="0" step="0.01" required class="w-28 rounded-md border-gray-300 text-sm">
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-gray-500">{{ __('ui.incentives.effective_from') }}</span>
                        <input type="date" name="effective_from" required class="rounded-md border-gray-300 text-sm">
                    </label>
                    <label class="text-sm">
                        <span class="mb-1 block text-gray-500">{{ __('ui.incentives.effective_to') }}</span>
                        <input type="date" name="effective_to" class="rounded-md border-gray-300 text-sm">
                    </label>
                    <button type="submit" class="rounded-md bg-lentera-700 px-4 py-2 text-sm font-medium text-white transition duration-150 hover:bg-lentera-800">{{ __('ui.incentives.add_rule') }}</button>
                </form>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.incentives.rule_name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.incentives.role') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.incentives.product') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.incentives.calc') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.incentives.effective') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.incentives.active') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rules as $rule)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium">{{ $rule->name }}</td>
                                <td class="px-4 py-3">{{ $rule->recipient_role }}</td>
                                <td class="px-4 py-3">{{ $rule->product?->sku ?? __('ui.incentives.product_all') }}</td>
                                <td class="px-4 py-3">{{ $rule->calculation_type === 'percent' ? $rule->value.'%' : \App\Support\Money::fromDecimal((string) $rule->value)->format() }}</td>
                                <td class="px-4 py-3 tnum">{{ $rule->effective_from->format('d/m/Y') }}{{ $rule->effective_to ? ' – '.$rule->effective_to->format('d/m/Y') : '' }}</td>
                                <td class="px-4 py-3">
                                    @if ($rule->is_active)
                                        <x-lentera.badge :active="true" />
                                    @else
                                        <x-lentera.badge :active="false" />
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('incentives.rules.toggle', $rule) }}">
                                        @csrf
                                        <button type="submit" class="text-sm text-lentera-800 underline">{{ $rule->is_active ? __('ui.incentives.deactivate') : __('ui.incentives.activate') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-lentera.empty-state>{{ __('ui.incentives.no_rules_yet') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</x-app-layout>
