<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.inbox.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.inbox.customer') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.inbox.latest') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.inbox.unread') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($conversations as $message)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $message->customer?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    <span class="text-xs {{ $message->direction === 'in' ? 'text-lentera-700' : 'text-gray-400' }}">{{ $message->direction === 'in' ? '↓' : '↑' }}</span>
                                    {{ \Illuminate\Support\Str::limit($message->body, 70) }}
                                    <div class="text-xs text-gray-400">{{ $message->created_at->format('d/m/Y H:i') }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @if (($unread[$message->customer_id] ?? 0) > 0)
                                        <span class="tnum rounded-full bg-gold/15 px-2.5 py-0.5 text-xs font-semibold text-gold-dark">{{ $unread[$message->customer_id] }}</span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($message->customer)
                                        <a href="{{ route('wa-inbox.show', $message->customer) }}" class="text-sm font-medium text-lentera-800 underline-offset-2 hover:underline">{{ __('ui.inbox.open') }}</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-lentera.empty-state>{{ __('ui.inbox.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($orphans->isNotEmpty())
                <h2 class="mb-2 mt-8 text-base font-semibold text-gray-800">{{ __('ui.inbox.orphans') }}</h2>
                <div class="rounded-lg bg-white p-4 shadow-soft">
                    @foreach ($orphans as $message)
                        <div class="border-b border-gray-50 py-2 text-sm last:border-0">
                            <span class="tnum font-medium text-gray-700">{{ $message->phone }}</span>
                            <span class="text-gray-600">— {{ $message->body }}</span>
                            <span class="text-xs text-gray-400">{{ $message->created_at->format('d/m/Y H:i') }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
