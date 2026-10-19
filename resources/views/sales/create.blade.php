<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.sales.create') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="mb-4 rounded-lg bg-white p-6 shadow-soft">
                <form method="GET" action="{{ route('sales.create') }}" class="flex items-end gap-3">
                    <div class="flex-1">
                        <x-input-label for="pelanggan" :value="__('ui.sales.customer')" />
                        <select id="pelanggan" name="pelanggan" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                            <option value="">—</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected($selectedCustomer?->id === $customer->id)>{{ $customer->name }} · {{ $customer->phone }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-secondary-button type="submit">{{ __('ui.search') }}</x-secondary-button>
                    @can('customers.create')
                        <a href="{{ route('customers.create') }}" class="text-sm text-lentera-800 underline">{{ __('ui.customers.create') }}</a>
                    @endcan
                </form>
            </div>

            @if ($selectedCustomer)
                <div class="rounded-lg bg-white p-6 shadow-soft">
                    <form method="POST" action="{{ route('sales.store') }}">
                        @csrf
                        <input type="hidden" name="customer_id" value="{{ $selectedCustomer->id }}">
                        <div class="grid gap-4">
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <x-input-label for="sale_date" :value="__('ui.sales.date')" />
                                    <x-text-input id="sale_date" name="sale_date" type="date" class="mt-1 block w-full" :value="old('sale_date', now()->format('Y-m-d'))" required />
                                </div>
                                <div>
                                    <x-input-label for="prescription_id" :value="__('ui.sales.prescription')" />
                                    <select id="prescription_id" name="prescription_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                        <option value="">{{ __('ui.sales.prescription_none') }}</option>
                                        @foreach ($prescriptions as $rx)
                                            <option value="{{ $rx->id }}" @selected((string) old('prescription_id') === (string) $rx->id)>{{ $rx->examined_at->format('d/m/Y') }} — OD {{ $rx->od_sph }}/{{ $rx->od_cyl }} · OS {{ $rx->os_sph }}/{{ $rx->os_cyl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <x-input-label for="tax_mode" :value="__('ui.sales.tax_mode')" />
                                    <select id="tax_mode" name="tax_mode" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                        <option value="inclusive" @selected(old('tax_mode', $taxMode) === 'inclusive')>{{ __('ui.sales.tax_inclusive') }}</option>
                                        <option value="exclusive" @selected(old('tax_mode', $taxMode) === 'exclusive')>{{ __('ui.sales.tax_exclusive') }}</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ __('ui.sales.items') }}</h2>
                                <div class="space-y-2">
                                    @for ($i = 0; $i < 4; $i++)
                                        <div class="grid grid-cols-12 items-end gap-2">
                                            <div class="col-span-4">
                                                <select name="items[{{ $i }}][product_id]" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                                    <option value="">— {{ __('ui.sales.item') }} —</option>
                                                    @foreach ($products as $product)
                                                        <option value="{{ $product->id }}" @selected((string) old("items.{$i}.product_id") === (string) $product->id)>{{ $product->typeLabel() }} · {{ $product->name }} ({{ $product->sku }})</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-span-1">
                                                <x-text-input name="items[{{ $i }}][qty]" type="number" min="1" class="block w-full tnum" :value="old('items.'.$i.'.qty', $i === 0 ? 1 : '')" placeholder="Qty" />
                                            </div>
                                            <div class="col-span-2">
                                                <x-text-input name="items[{{ $i }}][unit_price]" type="number" step="0.01" min="0" class="block w-full tnum" :value="old('items.'.$i.'.unit_price')" :placeholder="__('ui.sales.unit_price')" />
                                            </div>
                                            <div class="col-span-2">
                                                <x-text-input name="items[{{ $i }}][discount_amount]" type="number" step="0.01" min="0" class="block w-full tnum" :value="old('items.'.$i.'.discount_amount')" :placeholder="__('ui.sales.discount')" />
                                            </div>
                                            <div class="col-span-3">
                                                <select name="items[{{ $i }}][prescription_ref]" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                                    <option value="">{{ __('ui.sales.rx_ref') }}</option>
                                                    @foreach (['od', 'os', 'pair'] as $ref)
                                                        <option value="{{ $ref }}" @selected(old("items.{$i}.prescription_ref") === $ref)>{{ __('ui.sales.rx_refs.'.$ref) }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    @endfor
                                </div>
                                <p class="mt-1 text-xs text-gray-400">{{ __('ui.sales.unit_price') }} kosong = harga master produk. Baris tanpa produk diabaikan bila qty kosong — isi minimal satu baris lengkap.</p>
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <x-input-label for="discount_amount" :value="__('ui.sales.discount_transaction')" />
                                    <x-text-input id="discount_amount" name="discount_amount" type="number" step="0.01" min="0" class="mt-1 block w-full tnum" :value="old('discount_amount')" />
                                </div>
                                <div>
                                    <x-input-label for="doctor_id" :value="__('ui.sales.doctor')" />
                                    <select id="doctor_id" name="doctor_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                        <option value="">—</option>
                                        @foreach ($doctors as $doctor)
                                            <option value="{{ $doctor->id }}" @selected((string) old('doctor_id') === (string) $doctor->id)>{{ $doctor->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <x-input-label for="insurance_id" :value="__('ui.sales.insurance')" />
                                    <select id="insurance_id" name="insurance_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                        <option value="">—</option>
                                        @foreach ($insurances as $insurance)
                                            <option value="{{ $insurance->id }}" @selected((string) old('insurance_id') === (string) $insurance->id)>{{ $insurance->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <x-input-label for="insurance_claim_amount" :value="__('ui.sales.insurance_claim')" />
                                    <x-text-input id="insurance_claim_amount" name="insurance_claim_amount" type="number" min="0" step="0.01" class="mt-1 block w-full" :value="old('insurance_claim_amount')" :placeholder="__('ui.sales.insurance_claim_hint')" />
                                    <x-input-error :messages="$errors->get('insurance_claim_amount')" class="mt-2" />
                                </div>
                            </div>

                            <fieldset class="rounded-lg border border-gray-200 p-4" x-data="{ rows: [{ method: '', amount: '', reference: '' }] }">
                                <legend class="px-1 text-sm font-semibold text-lentera-900">{{ __('ui.sales.initial_payment') }}</legend>
                                <p class="mb-3 text-xs text-gray-500">{{ __('ui.sales.split_hint') }}</p>
                                <template x-for="(row, idx) in rows" :key="idx">
                                    <div class="mb-3 grid grid-cols-[1fr_1fr_1fr_auto] items-end gap-4">
                                        <div>
                                            <x-input-label :value="__('ui.sales.method')" />
                                            <select :name="`payments[${idx}][method]`" x-model="row.method" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                                <option value="">—</option>
                                                @foreach (\App\Modules\Sales\Models\SalePayment::METHODS as $method)
                                                    <option value="{{ $method }}">{{ __('ui.sales.methods.'.$method) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <x-input-label :value="__('ui.sales.amount')" />
                                            <input :name="`payments[${idx}][amount]`" x-model="row.amount" type="number" step="0.01" min="0" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500 tnum">
                                        </div>
                                        <div>
                                            <x-input-label :value="__('ui.sales.reference')" />
                                            <input :name="`payments[${idx}][reference]`" x-model="row.reference" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                        </div>
                                        <button type="button" x-show="rows.length > 1" @click="rows.splice(idx, 1)" class="pb-2 text-sm text-red-600 hover:underline">{{ __('ui.sales.remove_row') }}</button>
                                    </div>
                                </template>
                                <button type="button" @click="rows.push({ method: '', amount: '', reference: '' })" class="text-sm font-medium text-lentera-800 hover:underline">+ {{ __('ui.sales.add_method') }}</button>
                            </fieldset>

                            <fieldset class="rounded-lg border border-gray-200 p-4">
                                <legend class="px-1 text-sm font-semibold text-lentera-900">{{ __('ui.sales.installment_schedule') }} ({{ __('ui.sales.optional') }})</legend>
                                <p class="mb-3 text-xs text-gray-500">{{ __('ui.sales.schedule_hint') }}</p>
                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <x-input-label for="installment_count" :value="__('ui.sales.installment_count')" />
                                        <x-text-input id="installment_count" name="installment_count" type="number" min="1" max="36" class="mt-1 block w-full tnum" :value="old('installment_count')" />
                                    </div>
                                    <div>
                                        <x-input-label for="installment_first_due" :value="__('ui.sales.first_due_date')" />
                                        <x-text-input id="installment_first_due" name="installment_first_due" type="date" class="mt-1 block w-full" :value="old('installment_first_due')" />
                                    </div>
                                    <div>
                                        <x-input-label for="installment_interval_days" :value="__('ui.sales.interval_days')" />
                                        <x-text-input id="installment_interval_days" name="installment_interval_days" type="number" min="1" max="365" class="mt-1 block w-full tnum" :value="old('installment_interval_days', 30)" />
                                    </div>
                                </div>
                            </fieldset>

                            <div>
                                <x-input-label for="notes" :value="__('ui.sales.notes')" />
                                <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                        <div class="mt-6 flex items-center gap-3">
                            <a href="{{ route('sales.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                            <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
