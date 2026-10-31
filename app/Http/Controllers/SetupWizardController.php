<?php

namespace App\Http\Controllers;

use App\Models\ServiceConfig;
use App\Models\User;
use App\Services\MediaSyncService;
use App\Services\QbitService;
use App\Services\RadarrService;
use App\Services\SonarrService;
use App\Support\Permission;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SetupWizardController extends Controller
{
    public function index(): Response
    {
        $configs = ServiceConfig::query()->get()->keyBy('service');

        return Inertia::render('Setup/Wizard', [
            'configs' => [
                'radarr' => $this->publicConfig($configs->get('radarr')),
                'sonarr' => $this->publicConfig($configs->get('sonarr')),
                'qbittorrent' => $this->publicConfig($configs->get('qbittorrent'), hideApiKey: true),
            ],
            'setupComplete' => ServiceConfig::setupComplete(),
            'servicesConfigured' => ServiceConfig::servicesConfigured(),
            'hasAdminUser' => ServiceConfig::hasAdminUser(),
            'needsAdmin' => ! ServiceConfig::hasAdminUser(),
        ]);
    }

    public function test(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service' => 'required|in:radarr,sonarr,qbittorrent',
            'base_url' => 'nullable|string',
            'api_key' => 'nullable|string',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
        ]);

        try {
            $result = match ($data['service']) {
                'radarr' => RadarrService::testConnection(
                    (string) $data['base_url'],
                    (string) $data['api_key']
                ),
                'sonarr' => SonarrService::testConnection(
                    (string) $data['base_url'],
                    (string) $data['api_key']
                ),
                'qbittorrent' => QbitService::testConnection(
                    (string) $data['base_url'],
                    $data['username'] ?? null,
                    $data['password'] ?? null,
                    $data['api_key'] ?? null
                ),
            };

            $meta = [];

            if ($data['service'] === 'radarr') {
                $temp = new ServiceConfig([
                    'service' => 'radarr',
                    'base_url' => $data['base_url'],
                    'api_key' => $data['api_key'],
                    'is_enabled' => true,
                ]);
                $meta = (new RadarrService($temp))->getMetadataProfiles();
            }

            if ($data['service'] === 'sonarr') {
                $temp = new ServiceConfig([
                    'service' => 'sonarr',
                    'base_url' => $data['base_url'],
                    'api_key' => $data['api_key'],
                    'is_enabled' => true,
                ]);
                $meta = (new SonarrService($temp))->getMetadataProfiles();
            }

            return response()->json([
                ...$result,
                'metadata' => $meta,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function store(Request $request)
    {
        $creatingAdmin = ! ServiceConfig::hasAdminUser();
        $servicesReady = ServiceConfig::servicesConfigured();
        $adminOnly = $creatingAdmin && $servicesReady && $request->boolean('admin_only');
        $skipAdmin = $request->boolean('skip_admin');

        if ($adminOnly) {
            $data = $request->validate([
                'admin.name' => 'required|string|max:255',
                'admin.email' => 'required|email|max:255|unique:users,email',
                'admin.password' => ['required', 'confirmed', Password::defaults()],
            ]);

            $admin = User::create([
                'name' => $data['admin']['name'],
                'email' => $data['admin']['email'],
                'password' => $data['admin']['password'],
                'permissions' => Permission::ADMIN,
                'user_type' => 'local',
                'is_active' => true,
                'last_login_at' => now(),
            ]);

            Auth::login($admin);
            $request->session()->regenerate();

            return redirect()->route('discover');
        }

        $rules = [
            'radarr.base_url' => 'required|string',
            'radarr.api_key' => 'required|string',
            'radarr.defaults.qualityProfileId' => 'required|integer',
            'radarr.defaults.rootFolderPath' => 'required|string',
            'sonarr.enabled' => 'boolean',
            'sonarr.base_url' => 'nullable|string',
            'sonarr.api_key' => 'nullable|string',
            'sonarr.defaults.qualityProfileId' => 'nullable|integer',
            'sonarr.defaults.rootFolderPath' => 'nullable|string',
            'sonarr.defaults.languageProfileId' => 'nullable|integer',
            'qbittorrent.base_url' => 'required|string',
            'qbittorrent.api_key' => 'nullable|string',
            'qbittorrent.username' => 'nullable|string',
            'qbittorrent.password' => 'nullable|string',
        ];

        if ($creatingAdmin && ! $skipAdmin) {
            $rules['admin.name'] = 'required|string|max:255';
            $rules['admin.email'] = 'required|email|max:255|unique:users,email';
            $rules['admin.password'] = ['required', 'confirmed', Password::defaults()];
        }

        $data = $request->validate($rules);

        $qbitApiKey = $data['qbittorrent']['api_key'] ?? null;
        $qbitUser = $data['qbittorrent']['username'] ?? null;
        $qbitPass = $data['qbittorrent']['password'] ?? null;

        if (! filled($qbitApiKey) && (! filled($qbitUser) || ! filled($qbitPass))) {
            return back()->withErrors([
                'qbittorrent' => 'Provide a qBittorrent API key, or username + password.',
            ]);
        }

        $admin = null;

        DB::transaction(function () use ($data, $qbitApiKey, $qbitUser, $qbitPass, $creatingAdmin, $skipAdmin, &$admin) {
            // Ensure bundled TMDB is marked enabled so older checks stay happy.
            $this->upsert('tmdb', [
                'base_url' => config('omniseerr.tmdb_base_url'),
                'api_key' => config('omniseerr.tmdb_api_key'),
                'is_enabled' => true,
            ]);

            $this->upsert('radarr', [
                'base_url' => $data['radarr']['base_url'],
                'api_key' => $data['radarr']['api_key'],
                'defaults' => $data['radarr']['defaults'],
                'is_enabled' => true,
            ]);

            $sonarrEnabled = (bool) ($data['sonarr']['enabled'] ?? false)
                && filled($data['sonarr']['base_url'] ?? null)
                && filled($data['sonarr']['api_key'] ?? null);

            $this->upsert('sonarr', [
                'base_url' => $data['sonarr']['base_url'] ?? null,
                'api_key' => $data['sonarr']['api_key'] ?? null,
                'defaults' => $data['sonarr']['defaults'] ?? null,
                'is_enabled' => $sonarrEnabled,
            ]);

            $this->upsert('qbittorrent', [
                'base_url' => $data['qbittorrent']['base_url'],
                'api_key' => $qbitApiKey,
                'username' => $qbitUser,
                'password' => $qbitPass,
                'is_enabled' => true,
            ]);

            if ($creatingAdmin && ! $skipAdmin) {
                $admin = User::create([
                    'name' => $data['admin']['name'],
                    'email' => $data['admin']['email'],
                    'password' => $data['admin']['password'],
                    'permissions' => Permission::ADMIN,
                    'user_type' => 'local',
                    'is_active' => true,
                    'last_login_at' => now(),
                ]);
            }
        });

        Settings::setMain([
            'mediaServerType' => 'plex',
            'plexAllowNewUsers' => true,
            'newPlexLogin' => true,
            'localLogin' => true,
        ]);

        try {
            app(MediaSyncService::class)->syncAll();
        } catch (Throwable) {
            // First sync is best-effort; scheduled job will retry.
        }

        if ($admin) {
            Auth::login($admin);
            $request->session()->regenerate();

            return redirect()->route('discover');
        }

        // Services saved — first admin via Plex (or local login).
        return redirect()->route('login');
    }

    private function upsert(string $service, array $attributes): void
    {
        ServiceConfig::updateOrCreate(
            ['service' => $service],
            $attributes
        );
    }

    private function publicConfig(?ServiceConfig $config, bool $hideApiKey = false): ?array
    {
        if (! $config) {
            return null;
        }

        return [
            'base_url' => $config->base_url,
            'api_key' => $hideApiKey ? null : ($config->api_key ? '********' : null),
            'username' => $config->username,
            'has_password' => filled($config->password),
            'defaults' => $config->defaults,
            'is_enabled' => $config->is_enabled,
        ];
    }
}
