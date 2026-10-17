<x-print-layout :title="__('ui.print_doc.user_list')" :storeName="$storeName" :printedBy="$printedBy">
    <div class="doc-title">{{ __('ui.print_doc.user_list') }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ __('ui.users.name') }}</th>
                <th>{{ __('ui.users.username') }}</th>
                <th>{{ __('ui.users.roles') }}</th>
                <th>{{ __('ui.users.branches') }}</th>
                <th>{{ __('ui.users.status') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->username }}</td>
                    <td>{{ $user->roles->pluck('label')->join(', ') ?: '—' }}</td>
                    <td>{{ $user->hasGlobalAccess() ? __('ui.users.all_branches') : ($user->branches->pluck('name')->join(', ') ?: '—') }}</td>
                    <td>{{ $user->is_active ? __('ui.active') : __('ui.inactive') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-print-layout>
