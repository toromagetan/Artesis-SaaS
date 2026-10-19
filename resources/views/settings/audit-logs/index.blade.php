<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.audit_logs.title') }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
            <form method="GET" class="flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-soft">
                <label class="text-sm">
                    <span class="mb-1 block text-gray-500">{{ __('ui.audit_logs.action') }}</span>
                    <input type="text" name="aksi" value="{{ $filters['aksi'] ?? '' }}" class="rounded-md border-gray-300 text-sm" placeholder="sale.create">
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-gray-500">{{ __('ui.audit_logs.subject') }}</span>
                    <select name="subjek" class="rounded-md border-gray-300 text-sm">
                        <option value="">{{ __('ui.audit_logs.all') }}</option>
                        @foreach ($subjectTypes as $type)
                            <option value="{{ $type }}" @selected(($filters['subjek'] ?? '') === $type)>{{ class_basename($type) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-gray-500">{{ __('ui.audit_logs.actor') }}</span>
                    <select name="pengguna" class="rounded-md border-gray-300 text-sm">
                        <option value="">{{ __('ui.audit_logs.all') }}</option>
                        @foreach ($users as $u)
                            <option value="{{ $u->id }}" @selected((int) ($filters['pengguna'] ?? 0) === $u->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-gray-500">{{ __('ui.audit_logs.branch') }}</span>
                    <select name="cabang" class="rounded-md border-gray-300 text-sm">
                        <option value="">{{ __('ui.audit_logs.all') }}</option>
                        @foreach ($branches as $b)
                            <option value="{{ $b->id }}" @selected((int) ($filters['cabang'] ?? 0) === $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-gray-500">{{ __('ui.audit_logs.from') }}</span>
                    <input type="date" name="dari" value="{{ $filters['dari'] ?? '' }}" class="rounded-md border-gray-300 text-sm">
                </label>
                <label class="text-sm">
                    <span class="mb-1 block text-gray-500">{{ __('ui.audit_logs.to') }}</span>
                    <input type="date" name="sampai" value="{{ $filters['sampai'] ?? '' }}" class="rounded-md border-gray-300 text-sm">
                </label>
                <button type="submit" class="rounded-md bg-lentera-700 px-4 py-2 text-sm font-medium text-white transition duration-150 hover:bg-lentera-800">{{ __('ui.audit_logs.apply') }}</button>
            </form>

            <section class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.audit_logs.time') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.audit_logs.actor') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.audit_logs.action') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.audit_logs.subject') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.audit_logs.branch') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50">
                                <td class="px-4 py-3 tnum">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                                <td class="px-4 py-3">{{ $log->user?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('audit-logs.show', $log) }}" class="font-medium text-lentera-800 underline">{{ $log->action }}</a>
                                </td>
                                <td class="px-4 py-3">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</td>
                                <td class="px-4 py-3">{{ $log->branch?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-lentera.empty-state>{{ __('ui.audit_logs.empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="border-t border-gray-50 px-4 py-3">{{ $logs->links() }}</div>
            </section>
        </div>
    </div>
</x-app-layout>
