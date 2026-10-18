<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.lab_orders.detail') }} — {{ $order->number }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('lab-orders.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                @if ($order->status !== 'received')
                    <x-lentera.dropdown-aksi>
                        <form method="POST" action="{{ route('lab-orders.transition', $order) }}">
                            @csrf
                            <input type="hidden" name="to" value="{{ $order->status === 'waiting' ? 'ordered' : 'received' }}">
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition duration-150 hover:bg-lentera-50 hover:text-lentera-900">{{ __('ui.lab_orders.advance') }}: {{ __('ui.lab_orders.statuses.'.($order->status === 'waiting' ? 'ordered' : 'received')) }}</button>
                        </form>
                    </x-lentera.dropdown-aksi>
                @endif
            </div>

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid grid-cols-[180px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500">{{ __('ui.lab_orders.number') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $order->number }}</dd>
                    <dt class="text-gray-500">{{ __('ui.lab_orders.sale') }}</dt>
                    <dd class="font-medium text-gray-800"><a class="text-lentera-800 underline-offset-2 hover:underline" href="{{ route('sales.show', $order->sale) }}">{{ $order->sale->number }}</a> · {{ $order->sale->customer->name }} · {{ $order->sale->branch->name }}</dd>
                    <dt class="text-gray-500">{{ __('ui.lab_orders.lab_name') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $order->lab_name }}</dd>
                    <dt class="text-gray-500">{{ __('ui.lab.status') }}</dt>
                    <dd class="font-medium text-gray-800">{{ __('ui.lab_orders.statuses.'.$order->status) }}</dd>
                    <dt class="text-gray-500">{{ __('ui.lab_orders.sent_at') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $order->sent_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                    <dt class="text-gray-500">{{ __('ui.lab_orders.received_at') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $order->received_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                    <dt class="text-gray-500">{{ __('ui.lab_orders.cost') }}</dt>
                    <dd class="font-medium text-gray-800 tnum">{{ $order->labCost()->format() }}</dd>
                    @if ($journal)
                        <dt class="text-gray-500">{{ __('ui.lab_orders.journal') }}</dt>
                        <dd class="font-medium text-gray-800"><a class="text-lentera-800 underline-offset-2 hover:underline" href="{{ route('journals.show', $journal) }}">{{ $journal->number }}</a></dd>
                    @endif
                </dl>
            </div>

            @if ($order->status === 'ordered')
                <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.lab_orders.statuses.received') }}</h2>
                <div class="rounded-lg bg-white p-6 shadow-soft">
                    <form method="POST" action="{{ route('lab-orders.transition', $order) }}" class="flex flex-wrap items-end gap-4">
                        @csrf
                        <input type="hidden" name="to" value="received">
                        <div>
                            <label for="lab_cost" class="mb-1 block text-sm font-medium text-gray-700">{{ __('ui.lab_orders.cost') }}</label>
                            <input id="lab_cost" name="lab_cost" type="number" min="0" step="0.01" value="{{ old('lab_cost', (string) $order->lab_cost) }}" class="w-48 rounded-lg border-gray-300 text-sm tnum focus:border-lentera-500 focus:ring-lentera-500">
                        </div>
                        <button type="submit" class="rounded-lg bg-lentera-700 px-4 py-2 text-sm font-semibold text-white transition duration-150 hover:bg-lentera-800">{{ __('ui.lab_orders.advance') }}: {{ __('ui.lab_orders.statuses.received') }}</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
