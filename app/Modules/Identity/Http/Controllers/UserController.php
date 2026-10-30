<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private UserService $users) {}

    public function index(Request $request): View
    {
        $query = User::visibleInTenantManagement($request->user())
            ->with(['roles', 'branches'])->orderBy('name');
        if ($search = $request->string('q')->toString()) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        return view('identity.users.index', [
            'users' => $query->paginate(20)->withQueryString(),
            'q' => $search,
        ]);
    }

    /** Detail pengguna — HALAMAN PENUH dengan rute sendiri (F.2 butir 7). */
    public function show(Request $request, User $user): View
    {
        $this->ensureManageable($request->user(), $user);
        $user->load(['roles', 'branches']);

        return view('identity.users.show', compact('user'));
    }

    /** Dokumen cetak: daftar pengguna (A5, tab baru). */
    public function printList(Request $request): View
    {
        $users = User::visibleInTenantManagement($request->user())
            ->with(['roles', 'branches'])->orderBy('name')->get();

        return view('identity.users.print-list', [
            'users' => $users,
            'storeName' => Setting::value('store.name', 'Artesis'),
            'printedBy' => $request->user(),
        ]);
    }

    /** Dokumen cetak: profil satu pengguna (A5, tab baru). */
    public function printProfile(Request $request, User $user): View
    {
        $this->ensureManageable($request->user(), $user);
        $user->load(['roles', 'branches']);

        return view('identity.users.print-profile', [
            'user' => $user,
            'storeName' => Setting::value('store.name', 'Artesis'),
            'printedBy' => request()->user(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('identity.users.create', [
            'roles' => Role::orderBy('name')->get(),
            'branches' => Branch::where('tenant_id', $request->user()->tenant_id)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'branches' => ['array'],
            'branches.*' => ['integer', Rule::exists('branches', 'id')->where('tenant_id', $request->user()->tenant_id)],
        ]);

        $this->users->createUser($data, $request->user());

        return redirect()->route('users.index')->with('status', __('ui.users.created'));
    }

    public function edit(Request $request, User $user): View
    {
        $this->ensureManageable($request->user(), $user);

        return view('identity.users.edit', [
            'user' => $user->load(['roles', 'branches']),
            'roles' => Role::orderBy('name')->get(),
            'branches' => Branch::where('tenant_id', $request->user()->tenant_id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureManageable($request->user(), $user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'branches' => ['array'],
            'branches.*' => ['integer', Rule::exists('branches', 'id')->where('tenant_id', $request->user()->tenant_id)],
        ]);

        $this->users->updateUser($user, $data, $request->user());

        return redirect()->route('users.index')->with('status', __('ui.users.updated'));
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->ensureManageable($request->user(), $user);

        if ($user->is_active) {
            $this->users->deactivateUser($user, $request->user());
            $message = __('ui.users.deactivated');
        } else {
            $this->users->activateUser($user, $request->user());
            $message = __('ui.users.activated');
        }

        return back()->with('status', $message);
    }

    /**
     * Pagar akses satu pengguna dari pengelolaan tenant: pengguna platform
     * dan pengguna milik tenant lain tidak dapat dibuka/diubah sama sekali.
     */
    private function ensureManageable(User $actor, User $target): void
    {
        abort_if($target->is_platform, 403);
        abort_if($actor->tenant_id !== null && $target->tenant_id !== $actor->tenant_id, 403);
    }
}
