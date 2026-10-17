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
        $query = User::with(['roles', 'branches'])->orderBy('name');
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
    public function show(User $user): View
    {
        $user->load(['roles', 'branches']);

        return view('identity.users.show', compact('user'));
    }

    /** Dokumen cetak: daftar pengguna (A5, tab baru). */
    public function printList(Request $request): View
    {
        $users = User::with(['roles', 'branches'])->orderBy('name')->get();

        return view('identity.users.print-list', [
            'users' => $users,
            'storeName' => Setting::value('store.name', 'Lentera'),
            'printedBy' => $request->user(),
        ]);
    }

    /** Dokumen cetak: profil satu pengguna (A5, tab baru). */
    public function printProfile(User $user): View
    {
        $user->load(['roles', 'branches']);

        return view('identity.users.print-profile', [
            'user' => $user,
            'storeName' => Setting::value('store.name', 'Lentera'),
            'printedBy' => request()->user(),
        ]);
    }

    public function create(): View
    {
        return view('identity.users.create', [
            'roles' => Role::orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(),
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
            'branches.*' => ['integer', Rule::exists('branches', 'id')],
        ]);

        $this->users->createUser($data, $request->user());

        return redirect()->route('users.index')->with('status', __('ui.users.created'));
    }

    public function edit(User $user): View
    {
        return view('identity.users.edit', [
            'user' => $user->load(['roles', 'branches']),
            'roles' => Role::orderBy('name')->get(),
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8'],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
            'branches' => ['array'],
            'branches.*' => ['integer', Rule::exists('branches', 'id')],
        ]);

        $this->users->updateUser($user, $data, $request->user());

        return redirect()->route('users.index')->with('status', __('ui.users.updated'));
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($user->is_active) {
            $this->users->deactivateUser($user, $request->user());
            $message = __('ui.users.deactivated');
        } else {
            $this->users->activateUser($user, $request->user());
            $message = __('ui.users.activated');
        }

        return back()->with('status', $message);
    }
}
