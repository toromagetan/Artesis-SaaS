<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.wa.log_title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-4">
                <form method="GET" class="flex gap-2">
                    <x-text-input name="q" :value="$q" :placeholder="__('ui.wa.search_placeholder')" class="w-72" />
                    <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">{{ __('ui.wa.status') }}</option>
                        @foreach (['queued', 'sent', 'delivered', 'failed'] as $s)
                            <option value="{{ $s }}" @selected($status === $s)>{{ __('ui.wa.statuses.'.$s) }}</option>
                        @endforeach
                    </select>
                    <select name="pemicu" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                        <option value="">{{ __('ui.wa.trigger') }}</option>
                        @foreach (['manual', 'invoice', 'reminder', 'campaign', 'birthday', 'test', 'inbox'] as $t)
                            <option value="{{ $t }}" @selected($trigger === $t)>{{ __('ui.wa.triggers.'.$t) }}</option>
                        @endforeach
                    </select>
                    <x-secondary-button type="submit">{{ __('ui.search') }}</x-secondary-button>
                </form>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.wa.time') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.wa.recipient') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.wa.direction') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.wa.body') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.wa.trigger') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.wa.status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($messages as $message)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 whitespace-nowrap">{{ $message->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3">{{ $message->customer?->name ?? '—' }}<div class="text-xs text-gray-400">{{ $message->phone }}</div></td>
                                <td class="px-4 py-3">{{ $message->direction === 'in' ? __('ui.wa.dir_in') : __('ui.wa.dir_out') }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ \Illuminate\Support\Str::limit($message->body, 90) }}@if ($message->error)<div class="text-xs text-red-600">{{ $message->error }}</div>@endif</td>
                                <td class="px-4 py-3">{{ __('ui.wa.triggers.'.$message->trigger) }}</td>
                                <td class="px-4 py-3">
                                    @if ($message->status === 'delivered' || $message->status === 'sent')
                                        <span class="rounded bg-lentera-50 px-2 py-0.5 text-xs font-medium text-lentera-800">{{ __('ui.wa.statuses.'.$message->status) }}</span>
                                    @elseif ($message->status === 'failed')
                                        <span class="rounded bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">{{ __('ui.wa.statuses.failed') }}</span>
                                    @else
                                        <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ __('ui.wa.statuses.queued') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-lentera.empty-state>{{ __('ui.wa.log_empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $messages->links() }}</div>
        </div>
    </div>
</x-app-layout>
