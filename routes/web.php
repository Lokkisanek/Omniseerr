<?php

use App\Http\Controllers\DiscoverController;
use App\Http\Controllers\InteractiveGrabController;
use App\Http\Controllers\SetupWizardController;
use App\Http\Controllers\TelemetryController;
use App\Http\Middleware\EnsureSetupComplete;
use App\Http\Middleware\RedirectIfSetupComplete;
use App\Models\ServiceConfig;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ServiceConfig::setupComplete()
        ? redirect()->route('discover')
        : redirect()->route('setup');
});

Route::middleware(RedirectIfSetupComplete::class)->group(function () {
    Route::get('/setup', [SetupWizardController::class, 'index'])->name('setup');
    Route::post('/setup', [SetupWizardController::class, 'store'])->name('setup.store');
    Route::post('/setup/test', [SetupWizardController::class, 'test'])->name('setup.test');
});

Route::middleware(EnsureSetupComplete::class)->group(function () {
    Route::get('/discover', [DiscoverController::class, 'index'])->name('discover');
    Route::get('/activity', [TelemetryController::class, 'queue'])->name('activity');

    Route::prefix('api')->group(function () {
        Route::post('/interactive-grab/prepare', [InteractiveGrabController::class, 'prepare']);
        Route::post('/interactive-grab/execute', [InteractiveGrabController::class, 'execute']);
        Route::delete('/interactive-grab/cleanup/{radarrMovieId}', [InteractiveGrabController::class, 'cleanup']);

        Route::get('/telemetry/snapshot', [TelemetryController::class, 'snapshot']);
        Route::post('/telemetry/presence', [TelemetryController::class, 'presence']);
        Route::post('/telemetry/control', [TelemetryController::class, 'control']);
    });
});
