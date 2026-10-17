<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.search_page.title') }} {{ __('ui.search_page.for') }} "{{ $q }}"</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            @if ($users->isEmpty() && $branches->isEmpty())
                <div class="rounded-lg bg-white shadow-soft">
                    <x-lentera.empty-state>{{ __('ui.search_page.empty', ['q' => $q]) }}</x-lentera.empty-state>
                </div>
            @endif

            @if ($users->isNotEmpty())
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('ui.search_page.users') }}</h2>
                <div class="mb-6 overflow-hidden rounded-lg bg-white shadow-soft">
                    @foreach ($users as $user)
                        <a href="{{ route('users.show', $user) }}" class="flex items-center justify-between border-b border-gray-100 px-4 py-3 transition duration-150 last:border-0 hover:bg-lentera-50">
                            <span>
                                <span class="block font-medium text-gray-800">{{ $user->name }}</span>
                                <span class="block text-xs text-gray-400">{{ $user->username }}@if ($user->email) · {{ $user->email }}@endif</span>
                            </span>
                            <x-lentera.badge :active="$user->is_active" />
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($branches->isNotEmpty())
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('ui.search_page.branches') }}</h2>
                <div class="overflow-hidden rounded-lg bg-white shadow-soft">
                    @foreach ($branches as $branch)
                        <a href="{{ route('branches.show', $branch) }}" class="flex items-center justify-between border-b border-gray-100 px-4 py-3 transition duration-150 last:border-0 hover:bg-lentera-50">
                            <span>
                                <span class="block font-medium text-gray-800">{{ $branch->name }}</span>
                                <span class="block text-xs text-gray-400">{{ $branch->phone ?: '—' }}</span>
                            </span>
                            <x-lentera.badge :active="$branch->is_active" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
