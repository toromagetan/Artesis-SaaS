<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.wa.templates_title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            <p class="mb-4 text-sm text-gray-500">{{ __('ui.wa.templates_hint') }}</p>
            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.wa.template_key') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.wa.template_body') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($templates as $template)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ __('ui.wa.keys.'.$template->key) }}<div class="text-xs font-normal text-gray-400">{{ $template->key }}</div></td>
                                <td class="px-4 py-3 text-gray-600">{{ \Illuminate\Support\Str::limit($template->body, 140) }}
                                    <div class="mt-1 text-xs">
                                        @if ($template->approved_by !== null)
                                            <span class="font-medium text-lentera-700">{{ __('ui.wa.approval_state') }}: {{ __('ui.wa.approved_yes') }}</span>
                                        @else
                                            <span class="text-gold-dark">{{ __('ui.wa.approval_state') }}: {{ __('ui.wa.approved_no') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <x-lentera.row-actions>
                                        <x-lentera.menu-item :href="route('wa-templates.edit', $template)">{{ __('ui.edit') }}</x-lentera.menu-item>
                                        @can('wa.campaigns.approve')
                                            @if ($template->approved_by === null)
                                                <form method="POST" action="{{ route('wa-templates.approve', $template) }}">
                                                    @csrf
                                                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">{{ __('ui.wa.approve_template') }}</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </x-lentera.row-actions>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3"><x-lentera.empty-state>{{ __('ui.wa.templates_empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
