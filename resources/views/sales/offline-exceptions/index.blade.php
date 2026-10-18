<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.offline.exceptions_title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.offline.col_device') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.offline.col_local') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.offline.col_reason') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.offline.col_items') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.offline.col_status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($exceptions as $exception)
                            <tr class="border-b border-gray-50 align-top last:border-0">
                                <td class="px-4 py-3">
                                    <span class="font-medium text-gray-800">{{ $exception->device->name }}</span>
                                    <span class="block text-xs text-gray-400">{{ $exception->branch->name }} · {{ $exception->created_at->format('d/m/Y H:i') }}</span>
                                </td>
                                <td class="px-4 py-3 tnum">{{ $exception->local_no ?? '—' }}</td>
                                <td class="px-4 py-3">{{ __('ui.offline.reasons.'.$exception->reason) }}</td>
                                <td class="px-4 py-3">
                                    @foreach (($exception->payload['items'] ?? []) as $item)
                                        <span class="block">{{ $item['qty'] ?? '?' }} × {{ __('ui.offline.product_no', ['id' => $item['product_id'] ?? '?']) }}</span>
                                    @endforeach
                                </td>
                                <td class="px-4 py-3">
                                    @if ($exception->status === 'pending')
                                        <span class="rounded-full bg-gold/10 px-2.5 py-0.5 text-xs font-medium text-gold-dark ring-1 ring-inset ring-gold/50">{{ __('ui.offline.status_pending') }}</span>
                                    @elseif ($exception->status === 'resolved')
                                        <span class="rounded-full bg-lentera-50 px-2.5 py-0.5 text-xs font-medium text-lentera-700 ring-1 ring-inset ring-lentera-600/20">{{ __('ui.offline.status_resolved') }}</span>
                                    @else
                                        <span class="rounded-full bg-gray-50 px-2.5 py-0.5 text-xs font-medium text-gray-500 ring-1 ring-inset ring-gray-500/10">{{ __('ui.offline.status_discarded') }}</span>
                                    @endif
                                    @if ($exception->resolution_note)
                                        <span class="mt-1 block text-xs text-gray-400">{{ $exception->resolution_note }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($exception->status === 'pending')
                                        <x-lentera.row-actions>
                                            <form method="POST" action="{{ route('offline-exceptions.retry', $exception) }}">
                                                @csrf
                                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 transition duration-150 hover:bg-gray-100">{{ __('ui.offline.retry') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('offline-exceptions.discard', $exception) }}" onsubmit="return confirm('{{ __('ui.offline.discard_confirm') }}')" class="border-t border-gray-100">
                                                @csrf
                                                <input type="hidden" name="note" value="{{ __('ui.offline.discard_default_note') }}">
                                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 transition duration-150 hover:bg-red-50">{{ __('ui.offline.discard') }}</button>
                                            </form>
                                        </x-lentera.row-actions>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-lentera.empty-state>{{ __('ui.offline.exceptions_empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $exceptions->links() }}
        </div>
    </div>
</x-app-layout>
