{{-- Filter tanggal + cabang, dipakai bersama layar & ekspor. --}}
<form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
    @isset($from)
        <div>
            <x-input-label for="dari" :value="__('ui.reports.from')" />
            <x-text-input id="dari" name="dari" type="date" value="{{ $from }}" class="mt-1" />
        </div>
        <div>
            <x-input-label for="sampai" :value="__('ui.reports.to')" />
            <x-text-input id="sampai" name="sampai" type="date" value="{{ $to }}" class="mt-1" />
        </div>
    @endisset
    <div>
        <x-input-label for="cabang" :value="__('ui.reports.branch')" />
        <select id="cabang" name="cabang" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
            <option value="">{{ __('ui.reports.all_branches') }}</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected($selected === $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
    </div>
    @isset($type)
        <div>
            <x-input-label for="tipe" :value="__('ui.products.type')" />
            <select id="tipe" name="tipe" class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                <option value="">—</option>
                @foreach (['frame', 'lens', 'rx', 'softlens', 'accessory'] as $t)
                    <option value="{{ $t }}" @selected($type === $t)>{{ __('ui.products.types.'.$t) }}</option>
                @endforeach
            </select>
        </div>
    @endisset
    <x-secondary-button type="submit">{{ __('ui.reports.apply') }}</x-secondary-button>
    <x-lentera.dropdown-cetak>
        <x-lentera.menu-item :href="url()->current().'?'.http_build_query(array_merge(request()->query(), ['ekspor' => 1]))">{{ __('ui.reports.export_csv') }}</x-lentera.menu-item>
    </x-lentera.dropdown-cetak>
</form>
