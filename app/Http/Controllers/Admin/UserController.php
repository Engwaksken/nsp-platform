<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with('roles')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->query('q').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->filled('role'), fn ($query) => $query->role((string) $request->query('role')))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users, 'roles' => Role::query()->orderBy('name')->pluck('name')]);
    }

    public function create(Request $request): View
    {
        return view('admin.users.form', ['user' => new User(['is_active' => true]), 'roles' => $this->assignableRoles($request->user())]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['nullable', Password::min(12)->letters()->numbers()],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $roles = $this->checkedRoles($request->user(), $data['roles'] ?? []);

        $user = new User;
        $user->forceFill([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            // Without a password the person can only sign in through single sign-on.
            'password' => $data['password'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ])->save();
        $user->syncRoles($roles);
        Audit::log('user.created', $user, ['roles' => $roles]);

        return redirect()->route('admin.users.index')->with('status', 'User created.');
    }

    public function edit(Request $request, User $user): View
    {
        return view('admin.users.form', ['user' => $user, 'roles' => $this->assignableRoles($request->user())]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::min(12)->letters()->numbers()],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Only a super admin may change a privileged account.
        abort_if($user->hasAnyRole(User::PRIVILEGED_ROLES) && ! $actor->hasRole('super_admin'), 403, 'Only a super admin can change this account.');
        $roles = $this->checkedRoles($actor, $data['roles'] ?? []);
        $active = $request->boolean('is_active');
        if ($user->is($actor) && (! $active || ! array_intersect($roles, User::ADMIN_ROLES))) {
            return back()->withErrors(['roles' => 'You cannot deactivate yourself or remove your own admin access.']);
        }

        $before = ['roles' => $user->getRoleNames()->all(), 'is_active' => $user->is_active, 'email' => $user->email];
        $user->forceFill(['name' => $data['name'], 'email' => Str::lower($data['email']), 'is_active' => $active]);
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();
        $user->syncRoles($roles);
        Audit::log('user.updated', $user, [
            'before' => $before,
            'after' => ['roles' => $roles, 'is_active' => $active, 'email' => $user->email],
            'password_changed' => ! empty($data['password']),
        ]);

        return redirect()->route('admin.users.index')->with('status', 'User saved.');
    }

    private function assignableRoles(User $actor)
    {
        return Role::query()->orderBy('name')->pluck('name')
            ->reject(fn ($role) => in_array($role, User::PRIVILEGED_ROLES, true) && ! $actor->hasRole('super_admin'))
            ->values();
    }

    private function checkedRoles(User $actor, array $roles): array
    {
        $roles = array_values(array_unique($roles));
        if (! $actor->hasRole('super_admin') && array_intersect($roles, User::PRIVILEGED_ROLES)) {
            abort(403, 'Only a super admin can grant super admin or platform admin.');
        }

        return $roles;
    }
}
