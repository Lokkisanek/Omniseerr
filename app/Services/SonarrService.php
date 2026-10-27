<?php

namespace App\Services;

use App\Models\GhostMedia;
use App\Models\ServiceConfig;
use App\Services\Concerns\ResolvesServiceConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SonarrService
{
    use ResolvesServiceConfig;

    public function __construct(private ?ServiceConfig $config = null) {}

    private function client(int $timeout = 15): PendingRequest
    {
        $config = $this->resolveConfig('sonarr', $this->config);

        return Http::baseUrl($this->normalizedBaseUrl($config).'/api/v3')
            ->withHeaders([
                'X-Api-Key' => $config->api_key,
                'Accept' => 'application/json',
            ])
            ->timeout($timeout);
    }

    public static function testConnection(string $baseUrl, string $apiKey): array
    {
        $response = Http::baseUrl(rtrim($baseUrl, '/').'/api/v3')
            ->withHeaders(['X-Api-Key' => $apiKey, 'Accept' => 'application/json'])
            ->timeout(10)
            ->get('/system/status');

        $response->throw();

        return [
            'ok' => true,
            'message' => 'Sonarr connection successful.',
            'version' => $response->json('version'),
            'instanceName' => $response->json('instanceName'),
        ];
    }

    public function getMetadataProfiles(): array
    {
        $profiles = $this->client()->get('/qualityprofile')->throw()->json();
        $folders = $this->client()->get('/rootfolder')->throw()->json();
        $languageProfiles = $this->client()->get('/languageprofile')->json() ?? [];

        return [
            'profiles' => collect($profiles)->map(fn ($p) => [
                'id' => $p['id'],
                'name' => $p['name'],
            ])->values()->all(),
            'folders' => collect($folders)->map(fn ($f) => [
                'id' => $f['id'],
                'path' => $f['path'],
                'freeSpace' => $f['freeSpace'] ?? 0,
            ])->values()->all(),
            'languageProfiles' => collect($languageProfiles)->map(fn ($p) => [
                'id' => $p['id'],
                'name' => $p['name'],
            ])->values()->all(),
        ];
    }

    /**
     * Skeleton for series Ghost-Add. Full season/episode interactive grab lands in a later pass.
     */
    public function ensureSeriesExistsForInteractiveSearch(
        int $tmdbId,
        ?int $tvdbId = null,
        ?int $profileId = null,
        ?string $rootFolder = null
    ): array {
        if (! $tvdbId) {
            throw new RuntimeException('Sonarr interactive search requires a TVDB ID.');
        }

        $existing = $this->client()->get('/series', ['tvdbId' => $tvdbId])->throw()->json();

        if (! empty($existing)) {
            return $existing[0];
        }

        $lookup = $this->client()->get('/series/lookup', ['term' => "tvdb:{$tvdbId}"])->throw()->json();

        if (empty($lookup)) {
            throw new RuntimeException("Series with TVDB ID {$tvdbId} was not found in Sonarr lookup.");
        }

        $series = $lookup[0];
        unset($series['id']);

        $config = $this->resolveConfig('sonarr', $this->config);
        $defaults = $config->defaults ?? [];

        $payload = array_merge($series, [
            'qualityProfileId' => $profileId ?? ($defaults['qualityProfileId'] ?? null),
            'rootFolderPath' => $rootFolder ?? ($defaults['rootFolderPath'] ?? null),
            'monitored' => false,
            'seasonFolder' => true,
            'addOptions' => [
                'searchForMissingEpisodes' => false,
                'searchForCutoffUnmetEpisodes' => false,
            ],
        ]);

        if (! empty($defaults['languageProfileId'])) {
            $payload['languageProfileId'] = $defaults['languageProfileId'];
        }

        if (! $payload['qualityProfileId'] || ! $payload['rootFolderPath']) {
            throw new RuntimeException('Sonarr quality profile and root folder must be configured.');
        }

        $created = $this->client()->post('/series', $payload)->throw()->json();

        GhostMedia::updateOrCreate(
            [
                'media_type' => 'series',
                'tmdb_id' => $tmdbId,
            ],
            [
                'external_arr_id' => $created['id'],
            ]
        );

        return $created;
    }

    public function getQueue(): array
    {
        try {
            return $this->client()->get('/queue', [
                'page' => 1,
                'pageSize' => 100,
                'includeSeries' => true,
                'includeEpisode' => true,
            ])->throw()->json('records') ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function getLibraryStatesByTmdbIds(array $tmdbIds): array
    {
        if ($tmdbIds === []) {
            return [];
        }

        $series = $this->client()->get('/series')->throw()->json() ?? [];

        return collect($series)
            ->filter(fn ($s) => in_array($s['tmdbId'] ?? null, $tmdbIds, true))
            ->mapWithKeys(function (array $s) {
                $stats = $s['statistics'] ?? [];
                $percent = (float) ($stats['percentOfEpisodes'] ?? 0);
                $monitored = (bool) ($s['monitored'] ?? false);

                $status = match (true) {
                    $percent >= 100 => 'AVAILABLE',
                    $monitored => 'MONITORED',
                    default => 'UNREQUESTED',
                };

                return [
                    (int) $s['tmdbId'] => [
                        'status' => $status,
                        'sonarrId' => $s['id'],
                        'title' => $s['title'] ?? null,
                        'year' => $s['year'] ?? null,
                    ],
                ];
            })
            ->all();
    }
}
