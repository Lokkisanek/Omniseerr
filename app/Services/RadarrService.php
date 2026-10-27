<?php

namespace App\Services;

use App\Models\GhostMedia;
use App\Models\ServiceConfig;
use App\Services\Concerns\ResolvesServiceConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RadarrService
{
    use ResolvesServiceConfig;

    public function __construct(private ?ServiceConfig $config = null) {}

    private function client(int $timeout = 15): PendingRequest
    {
        $config = $this->resolveConfig('radarr', $this->config);

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
            'message' => 'Radarr connection successful.',
            'version' => $response->json('version'),
            'instanceName' => $response->json('instanceName'),
        ];
    }

    public function getMetadataProfiles(): array
    {
        $profiles = $this->client()->get('/qualityprofile')->throw()->json();
        $folders = $this->client()->get('/rootfolder')->throw()->json();
        $tags = $this->client()->get('/tag')->throw()->json();

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
            'tags' => $tags,
        ];
    }

    /**
     * Ensure the movie exists in Radarr so Interactive Search can score Custom Formats.
     * Ghost-adds with monitored=false and searchForMovie=false when missing.
     */
    public function ensureMovieExistsForInteractiveSearch(
        int $tmdbId,
        ?int $profileId = null,
        ?string $rootFolder = null
    ): array {
        $existing = $this->client()->get('/movie', ['tmdbId' => $tmdbId])->throw()->json();

        if (! empty($existing)) {
            return $existing[0];
        }

        $lookupResponse = $this->client()->get('/movie/lookup/tmdb', ['tmdbId' => $tmdbId]);

        if ($lookupResponse->status() === 404 || empty($lookupResponse->json())) {
            throw new RuntimeException("Movie with TMDB ID {$tmdbId} was not found in Radarr lookup.");
        }

        $lookup = $lookupResponse->json();
        unset($lookup['id']);

        $config = $this->resolveConfig('radarr', $this->config);
        $defaults = $config->defaults ?? [];

        $payload = array_merge($lookup, [
            'qualityProfileId' => $profileId ?? ($defaults['qualityProfileId'] ?? null),
            'rootFolderPath' => $rootFolder ?? ($defaults['rootFolderPath'] ?? null),
            'monitored' => false,
            'minimumAvailability' => 'released',
            'addOptions' => [
                'searchForMovie' => false,
            ],
        ]);

        if (! $payload['qualityProfileId'] || ! $payload['rootFolderPath']) {
            throw new RuntimeException('Radarr quality profile and root folder must be configured.');
        }

        $created = $this->client()->post('/movie', $payload)->throw()->json();

        GhostMedia::updateOrCreate(
            [
                'media_type' => 'movie',
                'tmdb_id' => $tmdbId,
            ],
            [
                'external_arr_id' => $created['id'],
            ]
        );

        return $created;
    }

    public function fetchInteractiveReleases(int $radarrMovieId): array
    {
        $releases = $this->client(60)->get('/release', ['movieId' => $radarrMovieId])->throw()->json();

        return collect($releases)->map(function (array $r) {
            return [
                'guid' => $r['guid'],
                'indexerId' => $r['indexerId'],
                'indexer' => $r['indexer'] ?? 'Unknown',
                'title' => $r['title'],
                'size' => $r['size'] ?? 0,
                'sizeFormatted' => round(($r['size'] ?? 0) / 1073741824, 2).' GB',
                'seeders' => $r['seeders'] ?? 0,
                'leechers' => $r['leechers'] ?? 0,
                'protocol' => $r['protocol'] ?? 'torrent',
                'quality' => $r['quality']['quality']['name'] ?? 'Unknown',
                'resolution' => $r['quality']['quality']['resolution'] ?? 0,
                'customFormats' => collect($r['customFormats'] ?? [])->pluck('name')->all(),
                'customFormatScore' => $r['customFormatScore'] ?? 0,
                'languages' => collect($r['languages'] ?? [])->pluck('name')->all(),
                'rejected' => $r['rejected'] ?? false,
                'rejections' => $r['rejections'] ?? [],
                'ageHours' => round(($r['ageMinutes'] ?? 0) / 60, 1),
                'infoUrl' => $r['infoUrl'] ?? null,
            ];
        })
            ->sortByDesc(fn ($r) => [
                ! $r['rejected'],
                $r['customFormatScore'],
                $r['seeders'],
            ])
            ->values()
            ->all();
    }

    public function grabRelease(int $radarrMovieId, string $guid, int $indexerId): array
    {
        $grabbed = $this->client(30)->post('/release', [
            'guid' => $guid,
            'indexerId' => $indexerId,
        ])->throw()->json();

        $movie = $this->client()->get("/movie/{$radarrMovieId}")->throw()->json();
        $movie['monitored'] = true;
        $this->client()->put("/movie/{$radarrMovieId}", $movie)->throw();

        GhostMedia::query()
            ->where('media_type', 'movie')
            ->where('external_arr_id', $radarrMovieId)
            ->delete();

        return $grabbed;
    }

    public function deleteGhostMovie(int $radarrMovieId): void
    {
        $ghost = GhostMedia::query()
            ->where('media_type', 'movie')
            ->where('external_arr_id', $radarrMovieId)
            ->first();

        if (! $ghost) {
            return;
        }

        $this->client()->delete("/movie/{$radarrMovieId}", [
            'deleteFiles' => false,
            'addImportExclusion' => false,
        ])->throw();

        $ghost->delete();
    }

    public function getQueue(): array
    {
        try {
            return $this->client()->get('/queue', [
                'page' => 1,
                'pageSize' => 100,
                'includeMovie' => true,
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

        $movies = $this->client()->get('/movie')->throw()->json() ?? [];

        return collect($movies)
            ->filter(fn ($m) => in_array($m['tmdbId'] ?? null, $tmdbIds, true))
            ->mapWithKeys(function (array $m) {
                $hasFile = (bool) ($m['hasFile'] ?? false);
                $monitored = (bool) ($m['monitored'] ?? false);

                $status = match (true) {
                    $hasFile => 'AVAILABLE',
                    $monitored => 'MONITORED',
                    default => 'UNREQUESTED',
                };

                return [
                    (int) $m['tmdbId'] => [
                        'status' => $status,
                        'radarrId' => $m['id'],
                        'title' => $m['title'] ?? null,
                        'year' => $m['year'] ?? null,
                    ],
                ];
            })
            ->all();
    }

    public function posterUrl(array $movieImages): ?string
    {
        $poster = collect($movieImages)->firstWhere('coverType', 'poster');

        if (! $poster) {
            return null;
        }

        if (! empty($poster['remoteUrl'])) {
            return $poster['remoteUrl'];
        }

        if (! empty($poster['url'])) {
            $config = $this->resolveConfig('radarr', $this->config);

            return $this->normalizedBaseUrl($config).$poster['url'];
        }

        return null;
    }
}
