<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PlexTvService;
use App\Support\Permission;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PlexAuthController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'authToken' => 'required|string',
        ]);

        try {
            $account = (new PlexTvService($data['authToken']))->getUser();
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'plex' => $e->getMessage() ?: 'Plex authentication failed.',
            ]);
        }

        $user = User::query()->where('plex_id', $account['id'])->first();

        if (! $user) {
            $user = User::query()->where('email', $account['email'])->first();
        }

        $isFirstUser = ! User::query()->exists();
        $allowNew = Settings::plexAllowNewUsers();

        if (! $user) {
            if (! $isFirstUser && ! $allowNew) {
                throw ValidationException::withMessages([
                    'plex' => 'Plex sign-in is restricted. Ask an admin to create your account.',
                ]);
            }

            $user = DB::transaction(function () use ($account, $isFirstUser) {
                return User::create([
                    'name' => $account['username'],
                    'email' => $account['email'],
                    // Unusable local password — Plex users sign in via OAuth only.
                    'password' => Str::password(64),
                    'permissions' => $isFirstUser ? Permission::ADMIN : Permission::DEFAULT_USER,
                    'user_type' => 'plex',
                    'plex_id' => $account['id'],
                    'plex_username' => $account['username'],
                    'plex_token' => $account['authToken'],
                    'avatar' => $account['thumb'],
                    'is_active' => true,
                    'last_login_at' => now(),
                ]);
            });
        } else {
            if (! $user->is_active) {
                throw ValidationException::withMessages([
                    'plex' => 'This account has been disabled.',
                ]);
            }

            $user->forceFill([
                'plex_id' => $account['id'],
                'plex_username' => $account['username'],
                'plex_token' => $account['authToken'],
                'avatar' => $account['thumb'] ?: $user->avatar,
                'user_type' => $user->user_type === 'local' ? 'local' : 'plex',
                'name' => $user->name ?: $account['username'],
                'last_login_at' => now(),
            ])->save();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('discover'));
    }
}
