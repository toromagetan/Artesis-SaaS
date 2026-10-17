<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.accounting.manual_journal') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <div class="mb-4">
                <a href="{{ route('journals.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
            </div>
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <p class="mb-4 text-sm text-gray-600">{{ __('ui.accounting.manual_hint') }}</p>
                <form method="POST" action="{{ route('journals.store') }}">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="journal_date" :value="__('ui.accounting.date')" />
                            <x-text-input id="journal_date" name="journal_date" type="date" class="mt-1 block w-full" :value="old('journal_date', now()->toDateString())" required />
                            <x-input-error :messages="$errors->get('journal_date')" class="mt-2" />
                            <x-input-error :messages="$errors->get('period')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="description" :value="__('ui.accounting.description')" />
                            <x-text-input id="description" name="description" class="mt-1 block w-full" :value="old('description')" maxlength="255" />
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>
                    </div>

                    <x-input-error :messages="$errors->get('lines')" class="mt-4" />
                    <table class="mt-4 min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-500">
                                <th class="px-2 py-2 font-medium">{{ __('ui.accounting.account') }}</th>
                                <th class="px-2 py-2 font-medium">{{ __('ui.accounting.branch') }}</th>
                                <th class="px-2 py-2 font-medium">{{ __('ui.accounting.debit') }}</th>
                                <th class="px-2 py-2 font-medium">{{ __('ui.accounting.credit') }}</th>
                                <th class="px-2 py-2 font-medium">{{ __('ui.accounting.memo') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @for ($i = 0; $i < 6; $i++)
                                <tr class="border-b border-gray-50">
                                    <td class="px-2 py-2">
                                        <select name="lines[{{ $i }}][account_id]" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                            <option value="">—</option>
                                            @foreach ($accounts as $account)
                                                <option value="{{ $account->id }}" @selected((string) old("lines.{$i}.account_id") === (string) $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get("lines.{$i}.account_id")" class="mt-1" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <select name="lines[{{ $i }}][branch_id]" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                            <option value="">—</option>
                                            @foreach ($branches as $branch)
                                                <option value="{{ $branch->id }}" @selected((string) old("lines.{$i}.branch_id") === (string) $branch->id)>{{ $branch->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="px-2 py-2">
                                        <x-text-input name="lines[{{ $i }}][debit]" type="number" step="0.01" min="0" class="block w-full tnum" :value="old("lines.{$i}.debit")" />
                                        <x-input-error :messages="$errors->get("lines.{$i}.debit")" class="mt-1" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <x-text-input name="lines[{{ $i }}][credit]" type="number" step="0.01" min="0" class="block w-full tnum" :value="old("lines.{$i}.credit")" />
                                        <x-input-error :messages="$errors->get("lines.{$i}.credit")" class="mt-1" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <x-text-input name="lines[{{ $i }}][memo]" class="block w-full" :value="old("lines.{$i}.memo")" maxlength="255" />
                                    </td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>

                    <div class="flex items-center justify-end gap-2 pt-4">
                        <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
