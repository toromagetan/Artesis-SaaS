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
                                        <x-lentera.aksi-baris>
                                            @if ($exception->reason === 'uuid_conflict')
                                                {{-- Konflik UUID tidak dapat dicoba ulang: UUID sudah milik transaksi lain. --}}
                                                <span class="block max-w-56 text-left text-xs text-gray-500">{{ __('ui.offline.uuid_conflict_note') }}</span>
                                            @else
                                            <form method="POST" action="{{ route('offline-exceptions.retry', $exception) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[13px] font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500" title="{{ __('ui.offline.retry') }}" aria-label="{{ __('ui.offline.retry') }}"><svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg><span class="sr-only">{{ __('ui.offline.retry') }}</span></button>
                                            </form>
                                            @endif
                                            @can('sales.exceptions')
                                            <form method="POST" action="{{ route('offline-exceptions.discard', $exception) }}" onsubmit="return confirm('{{ __('ui.offline.discard_confirm') }}')">
                                                @csrf
                                                <input type="hidden" name="note" value="{{ __('ui.offline.discard_default_note') }}">
                                                <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-red-300 bg-white px-2.5 py-1.5 text-[13px] font-medium text-red-600 shadow-soft transition duration-150 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-lentera-500" title="{{ __('ui.offline.discard') }}" aria-label="{{ __('ui.offline.discard') }}"><svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg><span class="sr-only">{{ __('ui.offline.discard') }}</span></button>
                                            </form>
                                            @endcan
                                        </x-lentera.aksi-baris>
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
