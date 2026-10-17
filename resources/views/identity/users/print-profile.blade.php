<x-print-layout :title="__('ui.print_doc.user_profile')" :storeName="$storeName" :printedBy="$printedBy">
    <div class="doc-title">{{ __('ui.print_doc.user_profile') }}</div>
    <dl>
        <dt>{{ __('ui.users.name') }}</dt>
        <dd>{{ $user->name }}</dd>
        <dt>{{ __('ui.users.username') }}</dt>
        <dd>{{ $user->username }}</dd>
        <dt>{{ __('ui.users.email') }}</dt>
        <dd>{{ $user->email ?: '—' }}</dd>
        <dt>{{ __('ui.users.phone') }}</dt>
        <dd>{{ $user->phone ?: '—' }}</dd>
        <dt>{{ __('ui.users.roles') }}</dt>
        <dd>{{ $user->roles->pluck('label')->join(', ') ?: '—' }}</dd>
        <dt>{{ __('ui.users.branches') }}</dt>
        <dd>{{ $user->hasGlobalAccess() ? __('ui.users.all_branches') : ($user->branches->pluck('name')->join(', ') ?: '—') }}</dd>
        <dt>{{ __('ui.users.status') }}</dt>
        <dd>{{ $user->is_active ? __('ui.active') : __('ui.inactive') }}</dd>
        <dt>{{ __('ui.users.member_since') }}</dt>
        <dd class="tnum">{{ $user->created_at?->format('d/m/Y') }}</dd>
    </dl>
</x-print-layout>
