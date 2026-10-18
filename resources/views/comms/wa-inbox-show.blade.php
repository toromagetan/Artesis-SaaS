<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-lentera-950">{{ __('ui.inbox.thread') }} — {{ $customer->name }}</h1>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-lentera-200 bg-lentera-50 px-4 py-3 text-sm text-lentera-800">{{ session('status') }}</div>
            @endif
            @if ($customer->wa_opt_out)
                <div class="mb-4 rounded-lg border border-gold/50 bg-gold/10 px-4 py-3 text-sm text-gold-dark">{{ __('ui.inbox.opt_out_note') }}</div>
            @endif

            <div class="mb-6 rounded-lg bg-white p-6 shadow-soft">
                <div class="space-y-3">
                    @forelse ($messages as $message)
                        <div class="flex {{ $message->direction === 'out' ? 'justify-end' : 'justify-start' }}">
                            <div class="max-w-[75%] rounded-2xl px-4 py-2.5 text-sm {{ $message->direction === 'out' ? 'bg-lentera-800 text-white rounded-br-sm' : 'bg-gray-100 text-gray-800 rounded-bl-sm' }}">
                                <p class="whitespace-pre-line">{{ $message->body }}</p>
                                <p class="mt-1 text-right text-[11px] {{ $message->direction === 'out' ? 'text-lentera-200' : 'text-gray-400' }}">
                                    {{ $message->created_at->format('d/m H:i') }}
                                    @if ($message->direction === 'out')
                                        · {{ __('ui.inbox.statuses.'.$message->status) }}
                                        @if ($message->error) · {{ $message->error }} @endif
                                    @endif
                                </p>
                            </div>
                        </div>
                    @empty
                        <x-lentera.empty-state>{{ __('ui.inbox.thread_empty') }}</x-lentera.empty-state>
                    @endforelse
                </div>
            </div>

            <form method="POST" action="{{ route('wa-inbox.reply', $customer) }}" class="rounded-lg bg-white p-6 shadow-soft">
                @csrf
                <x-input-label for="body" :value="__('ui.inbox.reply_label')" />
                <textarea id="body" name="body" rows="3" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-lentera-500 focus:ring-lentera-500">{{ old('body') }}</textarea>
                <p class="mt-1 text-xs text-gray-500">{{ __('ui.inbox.reply_hint') }}</p>
                @error('body') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <div class="mt-4 flex items-center gap-3">
                    <a href="{{ route('wa-inbox.index') }}" class="text-sm text-gray-600 underline">{{ __('ui.back') }}</a>
                    <x-primary-button>{{ __('ui.inbox.send_reply') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
