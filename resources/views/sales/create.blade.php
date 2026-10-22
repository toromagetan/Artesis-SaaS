<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.sales.create') }}</h1>
    </x-slot>

    {{-- Terminal POS satu halaman (ADR-019, model Artesis): pelanggan &
         produk dicari async, keranjang di klien, status DP/Lunas turunan
         dari pembayaran. Transport tetap POST formulir dengan kontrak
         field yang sama seperti formulir lama — server menghitung ulang
         seluruh total secara otoritatif. --}}
    <script>
        window.POS_INIT = {
            customer: @json($customerData),
            prescriptions: @json($prescriptionData),
            taxMode: @json($taxMode),
            taxRate: {{ (float) $taxRate }},
            urls: {
                products: @json(route('sales.search-products')),
                customers: @json(route('sales.search-customers')),
                prescriptions: @json(route('sales.customer-prescriptions', ['customer' => '__ID__'])),
            },
        };
    </script>

    <div class="py-8">
        <div class="mx-auto max-w-6xl sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('sales.store') }}" x-data="posTerminal()">
                @csrf

                <div class="mb-4 rounded-lg bg-white p-6 shadow-soft">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="relative">
                            <x-input-label :value="__('ui.sales.customer')" />
                            <template x-if="!customer">
                                <input type="text" x-model="customerQuery" @input.debounce.300ms="searchCustomers()"
                                       placeholder="{{ __('ui.sales.search_customer_ph') }}" autocomplete="off"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                            </template>
                            <template x-if="customer">
                                <div class="mt-1 flex items-center justify-between rounded-md border border-lentera-200 bg-lentera-50 px-3 py-2">
                                    <span class="text-sm font-medium text-lentera-950"><span x-text="customer.name"></span> <span class="font-normal text-gray-500" x-text="customer.phone ? '· ' + customer.phone : ''"></span></span>
                                    <button type="button" @click="clearCustomer()" class="text-sm text-lentera-800 underline">{{ __('ui.sales.change_customer') }}</button>
                                </div>
                            </template>
                            <input type="hidden" name="customer_id" :value="customer ? customer.id : ''">
                            <div x-show="customerResults.length > 0" class="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-md border border-gray-200 bg-white shadow-soft">
                                <template x-for="c in customerResults" :key="c.id">
                                    <button type="button" @click="pickCustomer(c)" class="block w-full px-3 py-2 text-left text-sm hover:bg-lentera-50">
                                        <span class="font-medium text-gray-800" x-text="c.name"></span>
                                        <span class="text-gray-500" x-text="c.phone ? ' · ' + c.phone : ''"></span>
                                    </button>
                                </template>
                            </div>
                            @can('customers.create')
                                <a href="{{ route('customers.create') }}" class="mt-1 inline-block text-sm text-lentera-800 underline">{{ __('ui.customers.create') }}</a>
                            @endcan
                        </div>
                        <div>
                            <x-input-label :value="__('ui.sales.prescription')" />
                            <select name="prescription_id" x-model="prescriptionId" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                <option value="">{{ __('ui.sales.prescription_none') }}</option>
                                <template x-for="rx in prescriptions" :key="rx.id">
                                    <option :value="rx.id" x-text="rx.label"></option>
                                </template>
                            </select>
                            <p x-show="selectedRx()" class="mt-1 text-xs text-gray-500" x-text="rxRecap()"></p>
                        </div>
                        <div>
                            <x-input-label :value="__('ui.sales.date')" />
                            <x-text-input name="sale_date" type="date" class="mt-1 block w-full" :value="now()->format('Y-m-d')" required />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label :value="__('ui.sales.doctor')" />
                                <select name="doctor_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                    <option value="">—</option>
                                    @foreach ($doctors as $doctor)
                                        <option value="{{ $doctor->id }}">{{ $doctor->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label :value="__('ui.sales.tax_mode')" />
                                <select name="tax_mode" x-model="taxMode" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                    <option value="inclusive">{{ __('ui.sales.tax_inclusive') }}</option>
                                    <option value="exclusive">{{ __('ui.sales.tax_exclusive') }}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4 rounded-lg bg-white p-6 shadow-soft">
                    <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ __('ui.sales.items') }}</h2>
                    <div class="relative mb-3">
                        <input type="text" x-model="productQuery" @input.debounce.300ms="searchProducts()"
                               placeholder="{{ __('ui.sales.search_product_ph') }}" autocomplete="off"
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <div x-show="productResults.length > 0" class="absolute z-20 mt-1 max-h-72 w-full overflow-auto rounded-md border border-gray-200 bg-white shadow-soft">
                            <template x-for="p in productResults" :key="p.id">
                                <button type="button" @click="addToCart(p)" class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-lentera-50">
                                    <span>
                                        <span class="font-medium text-gray-800" x-text="(p.parent_name ? p.parent_name + ' — ' : '') + p.name"></span>
                                        <span class="text-gray-500" x-text="' · ' + p.sku + ' · ' + p.type_label"></span>
                                    </span>
                                    <span class="tnum text-gray-600"><span x-text="fmt(p.sale_price)"></span> · {{ __('ui.sales.stock') }} <span x-text="p.stock === null ? '—' : p.stock"></span></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <p x-show="items.length === 0" class="py-4 text-center text-sm text-gray-400">{{ __('ui.sales.cart_empty') }}</p>

                    <div x-show="items.length > 0" class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-400">
                                    <th class="py-2 pr-2 font-medium">{{ __('ui.sales.item') }}</th>
                                    <th class="py-2 pr-2 font-medium">{{ __('ui.sales.rx_ref') }}</th>
                                    <th class="py-2 pr-2 font-medium">{{ __('ui.sales.qty') }}</th>
                                    <th class="py-2 pr-2 font-medium">{{ __('ui.sales.unit_price') }}</th>
                                    <th class="py-2 pr-2 font-medium">{{ __('ui.sales.discount') }}</th>
                                    <th class="py-2 pr-2 text-right font-medium">{{ __('ui.sales.line_total') }}</th>
                                    <th class="py-2 font-medium"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(item, idx) in items" :key="item.uid">
                                    <tr class="border-b border-gray-50 align-top">
                                        <td class="py-2 pr-2">
                                            <span class="font-medium text-gray-800" x-text="item.name"></span>
                                            <span class="block text-xs text-gray-400" x-text="item.sku + ' · ' + item.type_label"></span>
                                            <input type="hidden" :name="`items[${idx}][product_id]`" :value="item.product_id">
                                        </td>
                                        <td class="py-2 pr-2">
                                            <select x-show="item.type === 'rx'" :name="`items[${idx}][prescription_ref]`" x-bind:disabled="item.type !== 'rx'" x-model="item.prescription_ref" class="block w-32 rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                                @foreach (['od', 'os', 'pair'] as $ref)
                                                    <option value="{{ $ref }}">{{ __('ui.sales.rx_refs.'.$ref) }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="py-2 pr-2"><input type="number" min="1" :name="`items[${idx}][qty]`" x-model.number="item.qty" class="tnum block w-16 rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500"></td>
                                        <td class="py-2 pr-2"><input type="number" step="0.01" min="0" :name="`items[${idx}][unit_price]`" x-model="item.unit_price" class="tnum block w-28 rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500"></td>
                                        <td class="py-2 pr-2"><input type="number" step="0.01" min="0" :name="`items[${idx}][discount_amount]`" x-model="item.discount_amount" class="tnum block w-24 rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500"></td>
                                        <td class="tnum py-2 pr-2 text-right font-medium text-gray-800" x-text="fmt(lineTotal(item))"></td>
                                        <td class="py-2 text-right">
                                            <button type="button" x-show="item.type === 'rx'" @click="item.showPrecal = !item.showPrecal" class="mr-2 text-sm font-medium text-lentera-800 hover:underline">{{ __('ui.sales.precal') }} <span x-text="'(' + precalFilled(item) + '/11)'"></span></button>
                                            <button type="button" @click="items.splice(idx, 1)" class="text-sm text-red-600 hover:underline">{{ __('ui.sales.remove_row') }}</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    {{-- Panel precal per item RX — di luar tabel agar struktur
                         tabel valid; input tetap di dalam form yang sama. --}}
                    <template x-for="(item, idx) in items" :key="'precal-' + item.uid">
                        <div x-show="item.type === 'rx' && item.showPrecal" class="mt-3 rounded-lg border border-lentera-100 bg-lentera-50/50 px-4 py-3">
                            <p class="mb-2 text-xs text-gray-500"><span class="font-semibold text-gray-700">{{ __('ui.sales.precal') }} — <span x-text="item.name"></span>.</span> {{ __('ui.sales.precal_hint') }} <span x-show="selectedRx()" class="font-medium text-gray-600" x-text="rxRecap()"></span></p>
                            <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
                                @foreach (['pvr', 'pvl', 'a', 'b', 'dbl', 'ed', 'vd', 'pa', 'wrap'] as $key)
                                    <div>
                                        <label class="text-xs font-medium uppercase text-gray-500">{{ strtoupper($key) }}</label>
                                        <input type="text" inputmode="decimal" :name="`items[${idx}][precal][{{ $key }}]`" x-model="item.precal.{{ $key }}" class="tnum mt-0.5 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                    </div>
                                @endforeach
                                <div>
                                    <label class="text-xs font-medium uppercase text-gray-500">DE</label>
                                    <select :name="`items[${idx}][precal][de]`" x-model="item.precal.de" class="mt-0.5 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                        <option value="">—</option>
                                        <option value="R">R</option>
                                        <option value="L">L</option>
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <label class="text-xs font-medium uppercase text-gray-500">Type Frame</label>
                                    <input type="text" maxlength="100" :name="`items[${idx}][precal][type_frame]`" x-model="item.precal.type_frame" class="mt-0.5 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded-lg bg-white p-6 shadow-soft">
                        <div class="grid gap-4">
                            <div>
                                <x-input-label :value="__('ui.sales.insurance')" />
                                <select id="insurance_id" name="insurance_id" x-model="insuranceId" @change="onInsuranceChange()" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                    <option value="">—</option>
                                    @foreach ($insurances as $insurance)
                                        <option value="{{ $insurance->id }}" data-claim-value="{{ $insurance->claim_value }}">{{ $insurance->name }} ({{ __('ui.sales.insurance_max') }} {{ \App\Support\Money::fromDecimal((string) $insurance->claim_value)->format() }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label :value="__('ui.sales.insurance_claim')" />
                                <input id="insurance_claim_amount" name="insurance_claim_amount" type="number" min="0" step="0.01" x-model="claimAmount" class="tnum mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500" placeholder="{{ __('ui.sales.insurance_claim_hint') }}">
                            </div>
                            <div>
                                <x-input-label :value="__('ui.sales.discount_transaction')" />
                                <input name="discount_amount" type="number" step="0.01" min="0" x-model="txnDiscount" class="tnum mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                            </div>
                            <div>
                                <x-input-label :value="__('ui.sales.notes')" />
                                <textarea name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500"></textarea>
                            </div>
                        </div>

                        <fieldset class="mt-4 rounded-lg border border-gray-200 p-4">
                            <legend class="px-1 text-sm font-semibold text-lentera-900">{{ __('ui.sales.installment_schedule') }} ({{ __('ui.sales.optional') }})</legend>
                            <p class="mb-3 text-xs text-gray-500">{{ __('ui.sales.schedule_hint') }}</p>
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <x-input-label :value="__('ui.sales.installment_count')" />
                                    <x-text-input name="installment_count" type="number" min="1" max="36" class="tnum mt-1 block w-full" />
                                </div>
                                <div>
                                    <x-input-label :value="__('ui.sales.first_due_date')" />
                                    <x-text-input name="installment_first_due" type="date" class="mt-1 block w-full" />
                                </div>
                                <div>
                                    <x-input-label :value="__('ui.sales.interval_days')" />
                                    <x-text-input name="installment_interval_days" type="number" min="1" max="365" class="tnum mt-1 block w-full" value="30" />
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <div class="rounded-lg bg-white p-6 shadow-soft">
                        <fieldset>
                            <legend class="text-sm font-semibold text-lentera-900">{{ __('ui.sales.initial_payment') }}</legend>
                            <p class="mb-3 text-xs text-gray-500">{{ __('ui.sales.split_hint') }}</p>
                            <template x-for="(row, idx) in payments" :key="idx">
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
                                        <input :name="`payments[${idx}][amount]`" x-model="row.amount" type="number" step="0.01" min="0" class="tnum mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                    </div>
                                    <div>
                                        <x-input-label :value="__('ui.sales.reference')" />
                                        <input :name="`payments[${idx}][reference]`" x-model="row.reference" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                    </div>
                                    <button type="button" x-show="payments.length > 1" @click="payments.splice(idx, 1)" class="pb-2 text-sm text-red-600 hover:underline">{{ __('ui.sales.remove_row') }}</button>
                                </div>
                            </template>
                            <button type="button" @click="payments.push({ method: '', amount: '', reference: '' })" class="text-sm font-medium text-lentera-800 hover:underline">+ {{ __('ui.sales.add_method') }}</button>
                        </fieldset>
                    </div>
                </div>

                {{-- Footer ringkasan TETAP (Blueprint F.2): total selalu
                     terlihat; status bayar turunan dari pembayaran. --}}
                <div class="sticky bottom-3 mt-4 rounded-lg border border-lentera-100 bg-white px-5 py-4 shadow-soft">
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                        <span class="text-gray-500">{{ __('ui.sales.subtotal') }} <span class="tnum font-medium text-gray-800" x-text="fmt(subtotal())"></span></span>
                        <span class="text-gray-500">{{ __('ui.sales.discount_transaction') }} <span class="tnum font-medium text-gray-800" x-text="fmt(num(txnDiscount))"></span></span>
                        <span class="text-gray-500">{{ __('ui.sales.tax') }} <span class="tnum font-medium text-gray-800" x-text="fmt(taxAmount())"></span></span>
                        <span class="text-base">{{ __('ui.sales.total') }} <span class="tnum font-bold text-lentera-950" x-text="fmt(grandTotal())"></span></span>
                        <span class="text-gray-500">{{ __('ui.sales.paid') }} <span class="tnum font-medium text-gray-800" x-text="fmt(paidTotal())"></span></span>
                        <span class="text-gray-500">{{ __('ui.sales.remaining') }} <span class="tnum font-medium text-gray-800" x-text="fmt(Math.max(0, grandTotal() - paidTotal()))"></span></span>
                        <span x-show="paidTotal() >= grandTotal() && grandTotal() > 0" class="rounded-full bg-lentera-100 px-2.5 py-0.5 text-xs font-semibold text-lentera-900">{{ __('ui.sales.pay_paid') }}</span>
                        <span x-show="paidTotal() > 0 && paidTotal() < grandTotal()" class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">{{ __('ui.sales.pay_partial') }}</span>
                        <span x-show="paidTotal() <= 0" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">{{ __('ui.sales.pay_unpaid') }}</span>
                        <span class="ml-auto flex items-center gap-3">
                            <a href="{{ route('sales.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                            <button type="submit" name="intent" value="print" x-bind:disabled="!customer || items.length === 0" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 disabled:opacity-50">{{ __('ui.sales.save_print') }}</button>
                            <button type="submit" name="intent" value="wa" x-bind:disabled="!customer || items.length === 0" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 disabled:opacity-50">{{ __('ui.sales.save_wa') }}</button>
                            <x-primary-button x-bind:disabled="!customer || items.length === 0">{{ __('ui.save') }}</x-primary-button>
                        </span>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        function posTerminal() {
            const init = window.POS_INIT || {};
            return {
                customer: init.customer || null,
                customerQuery: '',
                customerResults: [],
                prescriptions: init.prescriptions || [],
                prescriptionId: '',
                productQuery: '',
                productResults: [],
                items: [],
                payments: [{ method: '', amount: '', reference: '' }],
                txnDiscount: '',
                insuranceId: '',
                claimAmount: '',
                taxMode: init.taxMode || 'inclusive',
                taxRate: init.taxRate || 11,
                _uid: 0,

                num(v) { const n = parseFloat(v); return isNaN(n) ? 0 : n; },
                fmt(n) {
                    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(this.num(n));
                },
                async searchCustomers() {
                    const q = this.customerQuery.trim();
                    const res = await fetch(init.urls.customers + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                    this.customerResults = res.ok ? await res.json() : [];
                },
                async pickCustomer(c) {
                    this.customer = c;
                    this.customerResults = [];
                    this.customerQuery = '';
                    this.prescriptionId = '';
                    const url = init.urls.prescriptions.replace('__ID__', c.id);
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    this.prescriptions = res.ok ? await res.json() : [];
                },
                clearCustomer() {
                    this.customer = null;
                    this.prescriptions = [];
                    this.prescriptionId = '';
                },
                selectedRx() {
                    return this.prescriptions.find(rx => String(rx.id) === String(this.prescriptionId)) || null;
                },
                rxRecap() {
                    const rx = this.selectedRx();
                    if (!rx) return '';
                    const f = (e) => 'SPH ' + e.sph + ' · CYL ' + e.cyl + ' · AXIS ' + e.axis + (e.add ? ' · ADD ' + e.add : '');
                    return 'OD: ' + f(rx.od) + ' | OS: ' + f(rx.os) + (rx.pd_total ? ' | PD ' + rx.pd_total : '');
                },
                async searchProducts() {
                    const q = this.productQuery.trim();
                    const res = await fetch(init.urls.products + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                    this.productResults = res.ok ? await res.json() : [];
                },
                addToCart(p) {
                    if (p.type !== 'rx') {
                        const existing = this.items.find(i => i.product_id === p.id);
                        if (existing) { existing.qty = this.num(existing.qty) + 1; this.productResults = []; return; }
                    }
                    this.items.push({
                        uid: ++this._uid,
                        product_id: p.id,
                        name: (p.parent_name ? p.parent_name + ' — ' : '') + p.name,
                        sku: p.sku,
                        type: p.type,
                        type_label: p.type_label,
                        qty: 1,
                        unit_price: p.sale_price,
                        discount_amount: '',
                        prescription_ref: 'pair',
                        precal: { pvr: '', pvl: '', a: '', b: '', dbl: '', ed: '', vd: '', pa: '', wrap: '', de: '', type_frame: '' },
                        showPrecal: false,
                    });
                    this.productResults = [];
                    this.productQuery = '';
                },
                lineTotal(item) {
                    return Math.max(0, this.num(item.qty) * this.num(item.unit_price) - this.num(item.discount_amount));
                },
                precalFilled(item) {
                    return Object.values(item.precal).filter(v => v !== '' && v !== null).length;
                },
                subtotal() {
                    return this.items.reduce((sum, i) => sum + this.lineTotal(i), 0);
                },
                taxAmount() {
                    const base = Math.max(0, this.subtotal() - this.num(this.txnDiscount));
                    if (this.taxMode === 'exclusive') return base * this.taxRate / 100;
                    return base - base / (1 + this.taxRate / 100);
                },
                grandTotal() {
                    const base = Math.max(0, this.subtotal() - this.num(this.txnDiscount));
                    return this.taxMode === 'exclusive' ? base + this.taxAmount() : base;
                },
                paidTotal() {
                    const rows = this.payments.reduce((sum, r) => sum + (r.method ? this.num(r.amount) : 0), 0);
                    return rows + this.num(this.claimAmount);
                },
                onInsuranceChange() {
                    // Paritas Artesis: memilih asuransi mengisi klaim
                    // sebesar claim_value master (dapat diubah kasir).
                    const select = document.getElementById('insurance_id');
                    const opt = select ? select.options[select.selectedIndex] : null;
                    const value = opt ? opt.getAttribute('data-claim-value') : null;
                    this.claimAmount = value ? value : '';
                },
            };
        }
    </script>
</x-app-layout>
