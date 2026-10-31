<?php

namespace App\Http\Middleware;

use App\Models\ServiceConfig;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $setupComplete = false;
        $settings = Settings::defaults();

        try {
            $setupComplete = ServiceConfig::setupComplete();
            $settings = Settings::main();
        } catch (\Throwable) {
            // Migrations may not have run yet during first boot.
        }

        return [
            ...parent::share($request),
            'appName' => $settings['applicationTitle'] ?? config('app.name', 'Omniseerr'),
            'setupComplete' => $setupComplete,
            'settings' => [
                'applicationTitle' => $settings['applicationTitle'] ?? 'Omniseerr',
                'hideAvailable' => (bool) ($settings['hideAvailable'] ?? false),
                'localLogin' => (bool) ($settings['localLogin'] ?? true),
                'plexEnabled' => ($settings['mediaServerType'] ?? 'plex') === 'plex'
                    && (bool) ($settings['newPlexLogin'] ?? true),
            ],
            'auth' => [
                'user' => $request->user()?->toInertia(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
