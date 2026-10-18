<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.members.adjust_points') }} — {{ $customer->name }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <p class="mb-4 text-sm text-gray-600">{{ __('ui.members.points_balance') }}: <span class="tnum font-semibold text-gray-800">{{ number_format($balance, 0, ',', '.') }}</span> · {{ __('ui.members.level') }}: <span class="font-medium text-gray-800">{{ $customer->level?->name ?? '—' }}</span></p>
                <form method="POST" action="{{ route('members.adjust.store', $customer) }}">
                    @csrf
                    <div class="grid gap-4">
                        <div>
                            <x-input-label for="points" :value="__('ui.members.points_delta')" />
                            <x-text-input id="points" name="points" type="number" step="1" class="mt-1 block w-full tnum" :value="old('points')" required />
                            <p class="mt-1 text-xs text-gray-500">{{ __('ui.members.points_delta_hint') }}</p>
                        </div>
                        <div>
                            <x-input-label for="reason" :value="__('ui.members.reason')" />
                            <x-text-input id="reason" name="reason" class="mt-1 block w-full" :value="old('reason')" required />
                        </div>
                    </div>
                    <div class="mt-6 flex items-center gap-3">
                        <a href="{{ route('members.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                        <x-primary-button>{{ __('ui.save') }}</x-primary-button>
                    </div>
                </form>
            </div>

            <h2 class="mb-2 text-base font-semibold text-gray-800">{{ __('ui.members.history') }}</h2>
            <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-500">
                            <th class="px-4 py-3 font-medium">{{ __('ui.members.time') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.members.type') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.members.points') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('ui.members.expires_at') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($history as $row)
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-4 py-3">{{ $row->created_at->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3">{{ __('ui.members.types.'.$row->type) }}</td>
                                <td class="px-4 py-3 tnum {{ $row->points < 0 ? 'text-red-600' : 'text-lentera-800' }}">{{ $row->points > 0 ? '+' : '' }}{{ number_format($row->points, 0, ',', '.') }}</td>
                                <td class="px-4 py-3">{{ $row->expires_at?->format('d/m/Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-lentera.empty-state>{{ __('ui.members.history_empty') }}</x-lentera.empty-state></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
