<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.customers.create') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <form method="POST" action="{{ route('customers.store') }}">
                    @csrf
                    <div class="grid gap-4">
                        <div>
                            <x-input-label for="name" :value="__('ui.customers.name')" />
                            <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="phone" :value="__('ui.customers.phone')" />
                            <x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone')" required />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="email" :value="__('ui.customers.email_optional')" />
                                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="birth_date" :value="__('ui.customers.birth_date_optional')" />
                                <x-text-input id="birth_date" name="birth_date" type="date" class="mt-1 block w-full" :value="old('birth_date')" />
                                <x-input-error :messages="$errors->get('birth_date')" class="mt-2" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="address" :value="__('ui.customers.address_optional')" />
                            <textarea id="address" name="address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">{{ old('address') }}</textarea>
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="branch_id" :value="__('ui.customers.home_branch')" />
                                <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('branch_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="insurance_id" :value="__('ui.customers.insurance')" />
                                <select id="insurance_id" name="insurance_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                    <option value="">{{ __('ui.customers.insurance_none') }}</option>
                                    @foreach ($insurances as $insurance)
                                        <option value="{{ $insurance->id }}" @selected((string) old('insurance_id') === (string) $insurance->id)>{{ $insurance->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('insurance_id')" class="mt-2" />
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex items-center gap-3">
                        <a href="{{ route('customers.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
