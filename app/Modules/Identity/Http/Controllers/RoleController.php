<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Services\RoleService;
use App\Support\PermissionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(private RoleService $roles) {}

    public function index(): View
    {
        return view('identity.roles.index', [
            'roles' => Role::withCount(['permissions', 'users'])->orderBy('name')->get(),
        ]);
    }

    public function edit(Role $role): View
    {
        return view('identity.roles.edit', [
            'role' => $role->load('permissions'),
            'groups' => PermissionRegistry::groups(),
            'granted' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);

        $this->roles->syncPermissions($role, $data['permissions'] ?? [], $request->user());

        return redirect()->route('roles.index')->with('status', __('ui.roles.updated'));
    }
}
