<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.purchasing.pr_create') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            <div class="rounded-lg bg-white p-6 shadow-soft">
                <form method="POST" action="{{ route('pr.store') }}">
                    @csrf
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="cabang_tujuan" :value="__('ui.purchasing.destination_branch')" />
                            <select id="cabang_tujuan" name="cabang_tujuan" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="notes" :value="__('ui.purchasing.notes')" />
                            <x-text-input id="notes" name="notes" class="mt-1 block w-full" :value="old('notes')" />
                        </div>
                    </div>

                    <h2 class="mb-2 mt-6 text-sm font-semibold text-gray-700">{{ __('ui.purchasing.items') }}</h2>
                    <div class="space-y-2">
                        @for ($i = 0; $i < 4; $i++)
                            <div class="grid grid-cols-12 gap-2">
                                <div class="col-span-5">
                                    <select name="items[{{ $i }}][product_id]" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                        <option value="">— {{ __('ui.purchasing.product') }} —</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <x-text-input name="items[{{ $i }}][qty]" type="number" min="1" class="block w-full tnum" placeholder="{{ __('ui.purchasing.qty') }}" />
                                </div>
                                <div class="col-span-2">
                                    <x-text-input name="items[{{ $i }}][estimated_price]" type="number" step="0.01" min="0" class="block w-full tnum" :placeholder="__('ui.purchasing.estimated_price')" />
                                </div>
                                <div class="col-span-3">
                                    <select name="items[{{ $i }}][supplier_id]" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                        <option value="">— {{ __('ui.purchasing.supplier') }} —</option>
                                        @foreach ($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endfor
                    </div>

                    <div class="mt-6 flex items-center gap-3">
                        <a href="{{ route('pr.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
