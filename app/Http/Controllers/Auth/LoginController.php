<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ServiceConfig;
use App\Models\User;
use App\Support\PlexClientId;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        $main = Settings::main();

        return Inertia::render('Auth/Login', [
            'canRegister' => false,
            'setupComplete' => ServiceConfig::setupComplete(),
            'hasUsers' => User::query()->exists(),
            'plexClientId' => PlexClientId::get(),
            'plexEnabled' => ($main['mediaServerType'] ?? 'plex') === 'plex' && ($main['newPlexLogin'] ?? true),
            'localLogin' => (bool) ($main['localLogin'] ?? true),
            'applicationTitle' => $main['applicationTitle'] ?? 'Omniseerr',
        ]);
    }

    public function plexLoading(): Response
    {
        return Inertia::render('Auth/PlexLoading');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'remember' => 'boolean',
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user && ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'This account has been disabled.',
            ]);
        }

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Invalid email or password.',
            ]);
        }

        $request->session()->regenerate();

        /** @var User $authUser */
        $authUser = Auth::user();
        $authUser->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('discover'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
