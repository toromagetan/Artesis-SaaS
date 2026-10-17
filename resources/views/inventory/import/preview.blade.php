<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.import.preview_title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="mb-6 grid gap-4 md:grid-cols-2">
                <div class="rounded-lg bg-white p-6 shadow-soft">
                    <p class="text-sm text-gray-500">{{ __('ui.import.valid_rows') }}</p>
                    <p class="text-2xl font-semibold tnum">{{ count($preview['valid']) }}</p>
                </div>
                <div class="rounded-lg bg-white p-6 shadow-soft">
                    <p class="text-sm text-gray-500">{{ __('ui.import.error_rows') }}</p>
                    <p class="text-2xl font-semibold tnum {{ count($preview['errors']) > 0 ? 'text-red-600' : '' }}">{{ count($preview['errors']) }}</p>
                </div>
            </div>

            @if (count($preview['errors']) > 0)
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-500">
                                <th class="px-4 py-3 font-medium">{{ __('ui.import.line') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('ui.stock.reason') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($preview['errors'] as $error)
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="px-4 py-3 tnum">{{ $error['line'] }}</td>
                                    <td class="px-4 py-3 text-red-600">{{ $error['message'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.import.line') }}</th>
                            <th class="px-4 py-3 font-medium">SKU</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.stock.branch') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.stock.qty') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($preview['valid'] as $row)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 tnum">{{ $row['line'] }}</td>
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $row['sku'] }}</td>
                                <td class="px-4 py-3">{{ $row['branch_name'] }}</td>
                                <td class="px-4 py-3 tnum">{{ $row['qty'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('stock.import.form') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                @if (count($preview['errors']) === 0 && count($preview['valid']) > 0)
                    <form method="POST" action="{{ route('stock.import.commit') }}" onsubmit="return confirm('{{ __('ui.import.commit') }}?')">
                        @csrf
                        <input type="hidden" name="token" value="{{ $preview['token'] }}">
                        <x-primary-button>{{ __('ui.import.commit') }}</x-primary-button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
