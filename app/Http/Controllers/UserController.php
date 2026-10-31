<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Users/Index', [
            'users' => User::query()
                ->orderByDesc('id')
                ->get()
                ->map(fn (User $user) => [
                    ...$user->toInertia(),
                    'createdAt' => $user->created_at?->toIso8601String(),
                    'lastLoginAt' => $user->last_login_at?->toIso8601String(),
                ]),
            'permissionOptions' => collect(Permission::labels())
                ->map(fn ($label, $value) => ['value' => (int) $value, 'label' => $label])
                ->values()
                ->all(),
            'defaultPermissions' => Permission::DEFAULT_USER,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::defaults()],
            'permissions' => 'required|integer|min:0',
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'permissions' => (int) $data['permissions'],
            'user_type' => 'local',
            'is_active' => true,
        ]);

        return redirect()->route('users.index');
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'permissions' => 'required|integer|min:0',
            'is_active' => 'required|boolean',
        ]);

        // Prevent locking yourself out of admin.
        if ($request->user()->id === $user->id) {
            $data['permissions'] = $data['permissions'] | Permission::ADMIN;
            $data['is_active'] = true;
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->permissions = (int) $data['permissions'];
        $user->is_active = (bool) $data['is_active'];

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        return redirect()->route('users.index');
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        if ($user->isAdmin() && User::query()->get()->filter->isAdmin()->count() <= 1) {
            return back()->withErrors(['user' => 'Cannot delete the last admin.']);
        }

        $user->delete();

        return redirect()->route('users.index');
    }
}
