<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.campaigns.title') }}</h1>
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

            <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.campaigns.name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.campaigns.template') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.campaigns.recipients') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($campaigns as $campaign)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    <a href="{{ route('wa-campaigns.show', $campaign) }}" class="text-lentera-800 underline-offset-2 hover:underline">{{ $campaign->name }}</a>
                                    <div class="text-xs font-normal text-gray-400">{{ $campaign->created_at->format('d/m/Y') }} · {{ $campaign->creator?->name }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $campaign->template?->key }} @if ($campaign->template && $campaign->template->approved_by === null) <span class="text-xs text-gold-dark">({{ __('ui.campaigns.template_unapproved_short') }})</span> @endif</td>
                                <td class="px-4 py-3 tnum">{{ $campaign->recipient_count }}</td>
                                <td class="px-4 py-3"><span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">{{ __('ui.campaigns.statuses.'.$campaign->status) }}</span></td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.aksi-baris>
                                        <x-lentera.menu-item :href="route('wa-campaigns.show', $campaign)" sm>{{ __('ui.campaigns.detail') }}</x-lentera.menu-item>
                                    </x-lentera.aksi-baris>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-lentera.empty-state>{{ __('ui.campaigns.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mb-6">{{ $campaigns->links() }}</div>

            <div class="rounded-lg bg-white p-6 shadow-soft">
                <h2 class="mb-4 text-base font-semibold text-gray-800">{{ __('ui.campaigns.create') }}</h2>
                <form method="POST" action="{{ route('wa-campaigns.store') }}" class="grid gap-4 sm:grid-cols-2">
                    @csrf
                    <div>
                        <x-input-label for="name" :value="__('ui.campaigns.name')" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" required />
                    </div>
                    <div>
                        <x-input-label for="template_id" :value="__('ui.campaigns.template')" />
                        <select id="template_id" name="template_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                            @foreach ($templates as $template)
                                <option value="{{ $template->id }}">{{ $template->key }}{{ $template->approved_by === null ? ' — '.__('ui.campaigns.template_unapproved_short') : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="segment_type" :value="__('ui.campaigns.segment')" />
                        <select id="segment_type" name="segment_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                            <option value="all">{{ __('ui.campaigns.segments.all') }}</option>
                            <option value="level">{{ __('ui.campaigns.segments.level') }}</option>
                            <option value="branch">{{ __('ui.campaigns.segments.branch') }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-input-label for="level_id" :value="__('ui.campaigns.level')" />
                            <select id="level_id" name="level_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                @foreach ($levels as $level)<option value="{{ $level->id }}">{{ $level->name }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="branch_id" :value="__('ui.campaigns.branch')" />
                            <select id="branch_id" name="branch_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">
                                @foreach ($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <p class="mb-3 text-xs text-gray-500">{{ __('ui.campaigns.flow_hint') }}</p>
                        <x-primary-button>{{ __('ui.campaigns.create') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
