<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ $campaign->name }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap gap-6 text-sm">
                        <div><span class="text-gray-500">{{ __('ui.status') }}:</span> <span class="font-medium">{{ __('ui.campaigns.statuses.'.$campaign->status) }}</span></div>
                        <div><span class="text-gray-500">{{ __('ui.campaigns.template') }}:</span> <span class="font-medium">{{ $campaign->template?->key }}</span></div>
                        <div><span class="text-gray-500">{{ __('ui.campaigns.approved_by') }}:</span> <span class="font-medium">{{ $campaign->approver?->name ?? '—' }}</span></div>
                        <div><span class="text-gray-500">{{ __('ui.campaigns.recipients') }}:</span> <span class="tnum font-medium">{{ $campaign->status === 'done' || $campaign->status === 'sending' ? $campaign->recipient_count : $previewCount }}</span></div>
                    </div>
                    <x-lentera.dropdown-aksi>
                        @can('wa.campaigns.approve')
                            @if ($campaign->status === 'draft')
                                <form method="POST" action="{{ route('wa-campaigns.approve', $campaign) }}">
                                    @csrf
                                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">{{ __('ui.campaigns.approve') }}</button>
                                </form>
                            @endif
                        @endcan
                        @if ($campaign->status === 'approved')
                            <form method="POST" action="{{ route('wa-campaigns.send', $campaign) }}" onsubmit="return confirm('{{ __('ui.campaigns.send_confirm') }}')">
                                @csrf
                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">{{ __('ui.campaigns.send') }}</button>
                            </form>
                        @endif
                        @if (in_array($campaign->status, ['draft', 'approved']))
                            <form method="POST" action="{{ route('wa-campaigns.cancel', $campaign) }}" onsubmit="return confirm('{{ __('ui.campaigns.cancel_confirm') }}')">
                                @csrf
                                <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">{{ __('ui.campaigns.cancel') }}</button>
                            </form>
                        @endif
                    </x-lentera.dropdown-aksi>
                </div>
                <p class="rounded bg-gray-50 p-3 text-sm text-gray-700">{{ $campaign->template?->body }}</p>
                @if ($campaign->template && $campaign->template->approved_by === null)
                    <p class="mt-3 rounded-lg border border-gold/50 bg-gold/10 px-3 py-2 text-sm text-gold-dark">{{ __('ui.campaigns.template_unapproved_note') }}</p>
                @endif
            </div>

            <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.campaigns.messages') }}</h2>
            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.campaigns.customer') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.campaigns.phone') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.status') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.campaigns.error') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($messages as $message)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $message->customer?->name ?? '—' }}</td>
                                <td class="px-4 py-3 tnum">{{ $message->phone }}</td>
                                <td class="px-4 py-3">{{ __('ui.inbox.statuses.'.$message->status) }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $message->error ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-lentera.empty-state>{{ __('ui.campaigns.messages_empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $messages->links() }}</div>
        </div>
    </div>
</x-app-layout>
