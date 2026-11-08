<?php

namespace App\Http\Controllers;

use App\Models\ServiceConfig;
use App\Services\MediaSyncService;
use App\Services\QbitService;
use App\Services\RadarrService;
use App\Services\SonarrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $configs = ServiceConfig::query()->get()->keyBy('service');

        return Inertia::render('Settings/Index', [
            'configs' => [
                'radarr' => $this->publicConfig($configs->get('radarr')),
                'sonarr' => $this->publicConfig($configs->get('sonarr')),
                'qbittorrent' => $this->publicConfig($configs->get('qbittorrent'), hideApiKey: true),
            ],
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

        $existing = ServiceConfig::for($data['service']);
        $apiKey = $this->resolveSecret($data['api_key'] ?? null, $existing?->api_key);
        $password = $this->resolveSecret($data['password'] ?? null, $existing?->password);

        try {
            $result = match ($data['service']) {
                'radarr' => RadarrService::testConnection(
                    (string) ($data['base_url'] ?: $existing?->base_url),
                    (string) $apiKey
                ),
                'sonarr' => SonarrService::testConnection(
                    (string) ($data['base_url'] ?: $existing?->base_url),
                    (string) $apiKey
                ),
                'qbittorrent' => QbitService::testConnection(
                    (string) ($data['base_url'] ?: $existing?->base_url),
                    $data['username'] ?? $existing?->username,
                    $password,
                    $apiKey
                ),
            };

            $meta = [];

            if ($data['service'] === 'radarr') {
                $temp = new ServiceConfig([
                    'service' => 'radarr',
                    'base_url' => $data['base_url'] ?: $existing?->base_url,
                    'api_key' => $apiKey,
                    'is_enabled' => true,
                ]);
                $meta = (new RadarrService($temp))->getMetadataProfiles();
            }

            if ($data['service'] === 'sonarr') {
                $temp = new ServiceConfig([
                    'service' => 'sonarr',
                    'base_url' => $data['base_url'] ?: $existing?->base_url,
                    'api_key' => $apiKey,
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

    public function update(Request $request)
    {
        $data = $request->validate([
            'radarr.base_url' => 'required|string',
            'radarr.api_key' => 'nullable|string',
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
        ]);

        $radarr = ServiceConfig::for('radarr');
        $sonarr = ServiceConfig::for('sonarr');
        $qbit = ServiceConfig::for('qbittorrent');

        $radarrKey = $this->resolveSecret($data['radarr']['api_key'] ?? null, $radarr?->api_key);
        $sonarrKey = $this->resolveSecret($data['sonarr']['api_key'] ?? null, $sonarr?->api_key);
        $qbitKey = $this->resolveSecret($data['qbittorrent']['api_key'] ?? null, $qbit?->api_key);
        $qbitPass = $this->resolveSecret($data['qbittorrent']['password'] ?? null, $qbit?->password);
        $qbitUser = filled($data['qbittorrent']['username'] ?? null)
            ? $data['qbittorrent']['username']
            : $qbit?->username;

        if (! filled($radarrKey)) {
            return back()->withErrors(['radarr.api_key' => 'Radarr API key is required.']);
        }

        if (! filled($qbitKey) && (! filled($qbitUser) || ! filled($qbitPass))) {
            return back()->withErrors([
                'qbittorrent' => 'Provide a qBittorrent API key, or username + password.',
            ]);
        }

        $sonarrEnabled = (bool) ($data['sonarr']['enabled'] ?? false)
            && filled($data['sonarr']['base_url'] ?? null)
            && filled($sonarrKey);

        if ($sonarrEnabled && (
            empty($data['sonarr']['defaults']['qualityProfileId'] ?? null)
            || empty($data['sonarr']['defaults']['rootFolderPath'] ?? null)
        )) {
            return back()->withErrors([
                'sonarr' => 'Enable Sonarr requires quality profile and root folder (Test Connection first).',
            ]);
        }

        DB::transaction(function () use ($data, $radarrKey, $sonarrKey, $qbitKey, $qbitUser, $qbitPass, $sonarrEnabled) {
            $this->upsert('tmdb', [
                'base_url' => config('omniseerr.tmdb_base_url'),
                'api_key' => config('omniseerr.tmdb_api_key'),
                'is_enabled' => true,
            ]);

            $this->upsert('radarr', [
                'base_url' => $data['radarr']['base_url'],
                'api_key' => $radarrKey,
                'defaults' => $data['radarr']['defaults'],
                'is_enabled' => true,
            ]);

            $this->upsert('sonarr', [
                'base_url' => $data['sonarr']['base_url'] ?? null,
                'api_key' => $sonarrKey,
                'defaults' => $data['sonarr']['defaults'] ?? null,
                'is_enabled' => $sonarrEnabled,
            ]);

            $this->upsert('qbittorrent', [
                'base_url' => $data['qbittorrent']['base_url'],
                'api_key' => $qbitKey,
                'username' => $qbitUser,
                'password' => $qbitPass,
                'is_enabled' => true,
            ]);
        });

        try {
            app(MediaSyncService::class)->syncAll();
        } catch (Throwable) {
            // Best-effort; scheduled job will retry.
        }

        return back()->with('success', 'Settings saved.');
    }

    private function resolveSecret(?string $incoming, ?string $existing): ?string
    {
        if (! filled($incoming) || $incoming === '********') {
            return $existing;
        }

        return $incoming;
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
            'api_key' => $hideApiKey
                ? ($config->api_key ? '********' : null)
                : ($config->api_key ? '********' : null),
            'username' => $config->username,
            'has_password' => filled($config->password),
            'has_api_key' => filled($config->api_key),
            'defaults' => $config->defaults,
            'is_enabled' => $config->is_enabled,
        ];
    }
}
