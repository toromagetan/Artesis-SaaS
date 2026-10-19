<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.audit_logs.detail') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
            <p><a href="{{ route('audit-logs.index') }}" class="text-sm text-lentera-800 underline">{{ __('ui.audit_logs.back') }}</a></p>

            <section class="rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-gray-500">{{ __('ui.audit_logs.time') }}</dt><dd class="font-medium tnum">{{ $log->created_at->format('d/m/Y H:i:s') }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.audit_logs.actor') }}</dt><dd class="font-medium">{{ $log->user?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.audit_logs.action') }}</dt><dd class="font-medium">{{ $log->action }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.audit_logs.subject') }}</dt><dd class="font-medium">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</dd></div>
                    <div><dt class="text-gray-500">{{ __('ui.audit_logs.branch') }}</dt><dd class="font-medium">{{ $log->branch?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-500">IP</dt><dd class="font-medium tnum">{{ $log->ip ?? '—' }}</dd></div>
                </dl>
            </section>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                    <h2 class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">{{ __('ui.audit_logs.old_values') }}</h2>
                    @if (! empty($log->old_values))
                        <table class="min-w-full text-sm">
                            <tbody>
                                @foreach ($log->old_values as $key => $value)
                                    <tr class="border-b border-gray-50 last:border-0">
                                        <td class="px-4 py-2 font-medium text-gray-600">{{ $key }}</td>
                                        <td class="px-4 py-2 text-right">{{ is_scalar($value) || $value === null ? (string) $value : json_encode($value) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <x-lentera.empty-state>{{ __('ui.audit_logs.none') }}</x-lentera.empty-state>
                    @endif
                </section>
                <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                    <h2 class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-800">{{ __('ui.audit_logs.new_values') }}</h2>
                    @if (! empty($log->new_values))
                        <table class="min-w-full text-sm">
                            <tbody>
                                @foreach ($log->new_values as $key => $value)
                                    <tr class="border-b border-gray-50 last:border-0">
                                        <td class="px-4 py-2 font-medium text-gray-600">{{ $key }}</td>
                                        <td class="px-4 py-2 text-right">{{ is_scalar($value) || $value === null ? (string) $value : json_encode($value) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <x-lentera.empty-state>{{ __('ui.audit_logs.none') }}</x-lentera.empty-state>
                    @endif
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
