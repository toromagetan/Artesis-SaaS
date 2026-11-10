<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Services\RoleService;
use App\Modules\Identity\Services\TenantContext;
use App\Support\PermissionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(private RoleService $roles) {}

    public function index(Request $request): View
    {
        $actor = $request->user();

        return view('identity.roles.index', [
            'roles' => Role::visibleTo($actor)
                ->withCount(['permissions', 'users'])->orderBy('name')->get(),
            'canCreate' => in_array(
                TenantContext::for($actor)->mode(),
                [TenantContext::MODE_PLATFORM, TenantContext::MODE_STANDALONE, TenantContext::MODE_TENANT],
                true,
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $context = TenantContext::for($actor);
        if (! in_array($context->mode(), [
            TenantContext::MODE_PLATFORM, TenantContext::MODE_STANDALONE, TenantContext::MODE_TENANT,
        ], true)) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'label' => ['required', 'string', 'max:100'],
        ]);

        // Peran baru menjadi milik tenant aktor (instalasi mandiri
        // & platform membuat templat global). Nama unik per lingkup.
        $tenantId = $context->mode() === TenantContext::MODE_TENANT ? $actor->tenant_id : null;
        $exists = Role::where('name', $data['name'])
            ->when($tenantId === null, fn ($q) => $q->whereNull('tenant_id'), fn ($q) => $q->where('tenant_id', $tenantId))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'name' => __('ui.roles.name_taken'),
            ]);
        }

        Role::create([
            'name' => $data['name'],
            'label' => $data['label'],
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('roles.index')->with('status', __('ui.roles.created'));
    }

    public function edit(Request $request, Role $role): View
    {
        abort_unless($role->isEditableBy($request->user()), 403);

        return view('identity.roles.edit', [
            'role' => $role->load('permissions'),
            'groups' => PermissionRegistry::groups(),
            'granted' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless($role->isEditableBy($request->user()), 403);

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $this->roles->syncPermissions($role, $data['permissions'] ?? [], $request->user());

        return redirect()->route('roles.index')->with('status', __('ui.roles.updated'));
    }
}
