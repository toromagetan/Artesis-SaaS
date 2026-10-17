<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.accounting.accounts') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <div class="mb-4">
                <a href="{{ route('journals.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
            </div>
            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.accounting.code') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.accounting.name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.accounting.group') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.accounting.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($accounts as $account)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium tnum">{{ $account->code }}</td>
                                <td class="px-4 py-3 text-gray-800">{{ $account->name }}</td>
                                <td class="px-4 py-3">{{ __('ui.accounting.groups.'.$account->group) }}</td>
                                <td class="px-4 py-3"><x-lentera.badge>{{ $account->is_active ? __('ui.active') : __('ui.inactive') }}</x-lentera.badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
