<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.qc.templates_title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif

            <p class="mb-4 text-sm text-gray-500">{{ __('ui.qc.templates_subtitle') }}</p>

            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('lab.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                @if ($canManage)
                    <x-lentera.aksi-bar>
                        <x-lentera.menu-item :href="route('qc-templates.create')" primary>{{ __('ui.qc.create') }}</x-lentera.menu-item>
                    </x-lentera.aksi-bar>
                @endif
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.qc.sort_order') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.qc.name') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.qc.required') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.accounting.status') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($templates as $template)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 tnum">{{ $template->sort_order }}</td>
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $template->name }}</td>
                                <td class="px-4 py-3">
                                    @if ($template->is_required)
                                        <span class="inline-block rounded-full border border-gold/50 bg-gold/10 px-2.5 py-0.5 text-[11px] font-semibold text-gold-dark">{{ __('ui.qc.required') }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3"><x-lentera.badge :active="$template->is_active" /></td>
                                <td class="px-4 py-3 text-right">
                                    @if ($canManage)
                                        <x-lentera.aksi-baris>
                                            <x-lentera.menu-item :href="route('qc-templates.edit', $template)" sm>{{ __('ui.edit') }}</x-lentera.menu-item>

                                            <form method="POST" action="{{ route('qc-templates.toggle', $template) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-[13px] font-medium shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500 {{ $template->is_active ? 'text-red-600' : 'text-lentera-700' }}">{{ $template->is_active ? __('ui.deactivate') : __('ui.activate') }}</button>
                                            </form>
                                        </x-lentera.aksi-baris>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6"><x-lentera.empty-state :message="__('ui.qc.empty')" /></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
