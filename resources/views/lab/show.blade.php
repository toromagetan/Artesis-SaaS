<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.lab.detail') }} — {{ $process->sale->number }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="mb-4 flex items-center justify-between">
                <a href="{{ route('lab.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                @unless ($process->isDone())
                    <x-lentera.aksi-bar>
                        @can('lab.assign')
                            @foreach (['edging', 'qc', 'ready'] as $next)
                                @if (['waiting' => 'edging', 'edging' => 'qc', 'qc' => 'ready'][$process->status] === $next)
                                    <form method="POST" action="{{ route('lab.transition', $process) }}">
                                        @csrf
                                        <input type="hidden" name="to" value="{{ $next }}">
                                        <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.lab.advance') }}: {{ __('ui.lab.statuses.'.$next) }}</button>
                                    </form>
                                @endif
                            @endforeach
                        @endcan
                        @can('lab.complete')
                            <form method="POST" action="{{ route('lab.complete', $process) }}" onsubmit="return confirm('{{ __('ui.lab.complete') }}?')">
                                @csrf
                                <button type="submit" class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 shadow-soft transition duration-150 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-lentera-500">{{ __('ui.lab.complete') }}</button>
                            </form>
                        @endcan
                    </x-lentera.aksi-bar>
                @endunless
            </div>

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <dl class="grid grid-cols-[180px_1fr] gap-y-3 text-sm">
                    <dt class="text-gray-500">{{ __('ui.lab.sale_number') }}</dt>
                    <dd class="font-medium text-gray-800"><a class="text-lentera-800 underline-offset-2 hover:underline" href="{{ route('sales.show', $process->sale) }}">{{ $process->sale->number }}</a></dd>
                    <dt class="text-gray-500">{{ __('ui.lab.customer') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $process->sale->customer->name }} · {{ $process->sale->customer->phone }}</dd>
                    <dt class="text-gray-500">{{ __('ui.sales.branch') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $process->sale->branch->name }}</dd>
                    <dt class="text-gray-500">{{ __('ui.lab.status') }}</dt>
                    <dd class="font-medium text-gray-800">{{ __('ui.lab.statuses.'.$process->status) }}</dd>
                    @if ($procurementStage !== null)
                        <dt class="text-gray-500">{{ __('ui.lab.lens_status') }}</dt>
                        <dd class="font-medium text-gray-800">{{ __('ui.lab.stages.'.$procurementStage) }}</dd>
                    @endif
                    <dt class="text-gray-500">{{ __('ui.lab.technician') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $process->technician?->name ?? __('ui.lab.unassigned') }}</dd>
                    <dt class="text-gray-500">{{ __('ui.lab.estimate') }}</dt>
                    <dd class="font-medium text-gray-800 tnum">{{ $process->estimated_days ?? '—' }}</dd>
                    <dt class="text-gray-500">{{ __('ui.lab.started_at') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $process->started_at->format('d/m/Y H:i') }}</dd>
                    <dt class="text-gray-500">{{ __('ui.lab.completed_at') }}</dt>
                    <dd class="font-medium text-gray-800">{{ $process->completed_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                    <dt class="text-gray-500">{{ __('ui.lab.duration') }}</dt>
                    <dd class="font-medium text-gray-800 tnum">{{ $process->durationDays() }}</dd>
                </dl>
            </div>

            @if ($process->sale->prescription_snapshot)
                <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.sales.prescription') }}</h2>
                <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach (['od' => 'eye_right', 'os' => 'eye_left'] as $side => $labelKey)
                            <div class="rounded-lg border border-gray-200 p-4">
                                <h3 class="mb-3 text-sm font-semibold text-lentera-900">{{ __('ui.prescriptions.'.$labelKey) }}</h3>
                                <dl class="grid grid-cols-2 gap-y-2 text-sm tnum">
                                    <dt class="text-gray-500">{{ __('ui.prescriptions.sph') }}</dt>
                                    <dd class="font-medium text-gray-800">{{ $process->sale->prescription_snapshot[$side]['sph'] }}</dd>
                                    <dt class="text-gray-500">{{ __('ui.prescriptions.cyl') }}</dt>
                                    <dd class="font-medium text-gray-800">{{ $process->sale->prescription_snapshot[$side]['cyl'] }}</dd>
                                    <dt class="text-gray-500">{{ __('ui.prescriptions.axis') }}</dt>
                                    <dd class="font-medium text-gray-800">{{ $process->sale->prescription_snapshot[$side]['axis'] ?? '—' }}</dd>
                                    <dt class="text-gray-500">{{ __('ui.prescriptions.add') }}</dt>
                                    <dd class="font-medium text-gray-800">{{ $process->sale->prescription_snapshot[$side]['add'] ?? '—' }}</dd>
                                </dl>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Daftar periksa QC (F2.2) --}}
            <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.qc.checklist') }}</h2>
            <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.qc.point') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.qc.latest') }}</th>
                            @can('lab.qc')
                                @unless ($process->isDone())
                                    <th class="px-4 py-3 font-medium">{{ __('ui.qc.record') }}</th>
                                @endunless
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($qcPoints as $point)
                            @php $latest = $qcLatest[$point->id] ?? null; @endphp
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3">
                                    <span class="font-medium text-gray-800">{{ $point->name }}</span>
                                    @if ($point->is_required)
                                        <span class="ml-1 inline-block rounded-full border border-gold/50 bg-gold/10 px-2 py-0.5 text-[11px] font-semibold text-gold-dark">{{ __('ui.qc.required') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($latest === null)
                                        <span class="inline-block rounded-full border border-gray-200 bg-gray-50 px-2.5 py-0.5 text-[11px] font-semibold text-gray-500">{{ __('ui.qc.not_checked') }}</span>
                                    @elseif ($latest->result === 'pass')
                                        <span class="inline-block rounded-full border border-lentera-200 bg-lentera-50 px-2.5 py-0.5 text-[11px] font-semibold text-lentera-700">{{ __('ui.qc.pass') }}</span>
                                        <span class="ml-1 text-xs text-gray-400">{{ __('ui.qc.attempt') }} {{ $latest->attempt }} · {{ $latest->checked_at->format('d/m/Y H:i') }}</span>
                                    @else
                                        <span class="inline-block rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-[11px] font-semibold text-red-600">{{ __('ui.qc.fail') }}</span>
                                        <span class="ml-1 text-xs text-gray-400">{{ __('ui.qc.attempt') }} {{ $latest->attempt }} · {{ $latest->checked_at->format('d/m/Y H:i') }}</span>
                                    @endif
                                </td>
                                @can('lab.qc')
                                    @unless ($process->isDone())
                                        <td class="px-4 py-3">
                                            <form method="POST" action="{{ route('lab.qc.record', $process) }}" class="flex flex-wrap items-center gap-2">
                                                @csrf
                                                <input type="hidden" name="point_id" value="{{ $point->id }}">
                                                <input type="text" name="notes" maxlength="255" placeholder="{{ __('ui.qc.notes') }}" class="w-44 rounded-lg border-gray-300 text-sm focus:border-lentera-500 focus:ring-lentera-500">
                                                <button type="submit" name="result" value="pass" class="rounded-lg bg-lentera-700 px-3 py-1.5 text-xs font-semibold text-white transition duration-150 hover:bg-lentera-800">{{ __('ui.qc.record_pass') }}</button>
                                                <button type="submit" name="result" value="fail" class="rounded-lg border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-600 transition duration-150 hover:bg-red-50">{{ __('ui.qc.record_fail') }}</button>
                                            </form>
                                        </td>
                                    @endunless
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6"><x-lentera.empty-state :message="__('ui.qc.empty')" /></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.qc.history') }}</h2>
            <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.qc.point') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.qc.attempt') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.qc.latest') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.qc.notes') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.qc.checked_by') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.qc.checked_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($qcHistory as $check)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3 text-gray-800">{{ $check->point->name }}</td>
                                <td class="px-4 py-3 tnum">{{ $check->attempt }}</td>
                                <td class="px-4 py-3">
                                    @if ($check->result === 'pass')
                                        <span class="inline-block rounded-full border border-lentera-200 bg-lentera-50 px-2.5 py-0.5 text-[11px] font-semibold text-lentera-700">{{ __('ui.qc.pass') }}</span>
                                    @else
                                        <span class="inline-block rounded-full border border-red-200 bg-red-50 px-2.5 py-0.5 text-[11px] font-semibold text-red-600">{{ __('ui.qc.fail') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $check->notes ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $check->checker->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $check->checked_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6"><x-lentera.empty-state :message="__('ui.qc.history_empty')" /></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @can('lab.assign')
                @unless ($process->isDone())
                    <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.lab.assign') }}</h2>
                    <div class="rounded-lg bg-white p-6 shadow-soft">
                        <form method="POST" action="{{ route('lab.assign', $process) }}" class="flex flex-wrap items-end gap-4">
                            @csrf
                            <div>
                                <x-input-label for="technician_id" :value="__('ui.lab.technician')" />
                                <select id="technician_id" name="technician_id" class="mt-1 block w-64 rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500" required>
                                    <option value="">—</option>
                                    @foreach ($technicians as $technician)
                                        <option value="{{ $technician->id }}" @selected($process->technician_id === $technician->id)>{{ $technician->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="estimated_days" :value="__('ui.lab.estimate')" />
                                <x-text-input id="estimated_days" name="estimated_days" type="number" min="1" max="90" class="mt-1 block w-32 tnum" :value="$process->estimated_days" />
                            </div>
                            <x-primary-button>{{ __('ui.lab.assign_save') }}</x-primary-button>
                        </form>
                    </div>
                @endunless
            @endcan
        </div>
    </div>
</x-app-layout>
