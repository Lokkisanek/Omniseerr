<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PlexAuthController;
use App\Http\Controllers\DiscoverController;
use App\Http\Controllers\InteractiveGrabController;
use App\Http\Controllers\SetupWizardController;
use App\Http\Controllers\TelemetryController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureSetupComplete;
use App\Http\Middleware\RedirectIfSetupComplete;
use App\Models\ServiceConfig;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check() && ServiceConfig::setupComplete()) {
        return redirect()->route('discover');
    }

    // Services ready but no admin yet → prefer Plex/local login (creates first admin).
    if (ServiceConfig::servicesConfigured() && ! ServiceConfig::hasAdminUser()) {
        return redirect()->route('login');
    }

    if (! ServiceConfig::setupComplete()) {
        return redirect()->route('setup');
    }

    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/login/plex/loading', [LoginController::class, 'plexLoading'])->name('login.plex.loading');
    Route::post('/auth/plex', [PlexAuthController::class, 'store'])->name('auth.plex');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(RedirectIfSetupComplete::class)->group(function () {
    Route::get('/setup', [SetupWizardController::class, 'index'])->name('setup');
    Route::post('/setup', [SetupWizardController::class, 'store'])->name('setup.store');
    Route::post('/setup/test', [SetupWizardController::class, 'test'])->name('setup.test');
});

Route::middleware(['auth', EnsureSetupComplete::class])->group(function () {
    Route::get('/discover', [DiscoverController::class, 'index'])->name('discover');

    Route::get('/activity', [TelemetryController::class, 'queue'])
        ->middleware(EnsurePermission::class.':RECENT_VIEW,MANAGE_REQUESTS,ADMIN')
        ->name('activity');

    Route::get('/users', [UserController::class, 'index'])
        ->middleware(EnsurePermission::class.':MANAGE_USERS,ADMIN')
        ->name('users.index');
    Route::post('/users', [UserController::class, 'store'])
        ->middleware(EnsurePermission::class.':MANAGE_USERS,ADMIN')
        ->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware(EnsurePermission::class.':MANAGE_USERS,ADMIN')
        ->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])
        ->middleware(EnsurePermission::class.':MANAGE_USERS,ADMIN')
        ->name('users.destroy');

    Route::prefix('api')->group(function () {
        Route::post('/interactive-grab/prepare', [InteractiveGrabController::class, 'prepare'])
            ->middleware(EnsurePermission::class.':REQUEST,REQUEST_MOVIE,ADMIN');
        Route::post('/interactive-grab/execute', [InteractiveGrabController::class, 'execute'])
            ->middleware(EnsurePermission::class.':REQUEST,REQUEST_MOVIE,ADMIN');
        Route::delete('/interactive-grab/cleanup/{radarrMovieId}', [InteractiveGrabController::class, 'cleanup'])
            ->middleware(EnsurePermission::class.':REQUEST,REQUEST_MOVIE,ADMIN');

        Route::get('/telemetry/snapshot', [TelemetryController::class, 'snapshot'])
            ->middleware(EnsurePermission::class.':RECENT_VIEW,MANAGE_REQUESTS,ADMIN');
        Route::post('/telemetry/presence', [TelemetryController::class, 'presence'])
            ->middleware(EnsurePermission::class.':RECENT_VIEW,MANAGE_REQUESTS,ADMIN');
        Route::post('/telemetry/control', [TelemetryController::class, 'control'])
            ->middleware(EnsurePermission::class.':MANAGE_REQUESTS,ADMIN');
    });
});
