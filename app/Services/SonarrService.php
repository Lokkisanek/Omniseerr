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

        $languageProfiles = [];
        try {
            $languageProfiles = $this->client()->get('/languageprofile')->json() ?? [];
        } catch (\Throwable) {
            // Sonarr v4 removed language profiles.
        }

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

    public function listSeries(): array
    {
        return $this->client(60)->get('/series')->throw()->json() ?? [];
    }

    /**
     * Ghost-add series (monitored=false, no auto-search) so interactive release search works.
     */
    public function ensureSeriesExistsForInteractiveSearch(
        int $tmdbId,
        ?int $tvdbId = null,
        ?int $profileId = null,
        ?string $rootFolder = null
    ): array {
        $existing = collect($this->listSeries())->first(
            fn (array $s) => (int) ($s['tmdbId'] ?? 0) === $tmdbId
                || ($tvdbId && (int) ($s['tvdbId'] ?? 0) === $tvdbId)
        );

        if ($existing) {
            return $existing;
        }

        $lookup = $this->lookupSeries($tmdbId, $tvdbId);

        unset($lookup['id'], $lookup['path']);

        $config = $this->resolveConfig('sonarr', $this->config);
        $defaults = $config->defaults ?? [];

        $qualityProfileId = $profileId ?? ($defaults['qualityProfileId'] ?? null);
        $rootFolderPath = $rootFolder ?? ($defaults['rootFolderPath'] ?? null);

        if (! $qualityProfileId || ! $rootFolderPath) {
            throw new RuntimeException('Sonarr quality profile and root folder must be configured.');
        }

        // Ghost: unmonitored seasons, no automatic search.
        $seasons = collect($lookup['seasons'] ?? [])
            ->map(fn (array $season) => array_merge($season, [
                'monitored' => false,
            ]))
            ->values()
            ->all();

        $payload = array_merge($lookup, [
            'qualityProfileId' => $qualityProfileId,
            'rootFolderPath' => $rootFolderPath,
            'monitored' => false,
            'seasonFolder' => true,
            'seasons' => $seasons,
            'addOptions' => [
                'searchForMissingEpisodes' => false,
                'searchForCutoffUnmetEpisodes' => false,
                'monitor' => 'none',
            ],
        ]);

        if (! empty($defaults['languageProfileId'])) {
            $payload['languageProfileId'] = $defaults['languageProfileId'];
        }

        // Prefer series type from lookup; default standard.
        $payload['seriesType'] = $lookup['seriesType'] ?? 'standard';

        $created = $this->client()->post('/series', $payload)->throw()->json();

        GhostMedia::updateOrCreate(
            [
                'media_type' => 'tv',
                'tmdb_id' => $tmdbId,
            ],
            [
                'external_arr_id' => $created['id'],
            ]
        );

        return $created;
    }

    /**
     * @return array<string, mixed>
     */
    private function lookupSeries(int $tmdbId, ?int $tvdbId): array
    {
        $terms = ["tmdb:{$tmdbId}"];
        if ($tvdbId) {
            array_unshift($terms, "tvdb:{$tvdbId}");
        }

        foreach ($terms as $term) {
            $lookup = $this->client()->get('/series/lookup', ['term' => $term])->throw()->json() ?? [];
            if (! empty($lookup)) {
                // Prefer exact tmdb match when present.
                $exact = collect($lookup)->first(
                    fn (array $s) => (int) ($s['tmdbId'] ?? 0) === $tmdbId
                );

                return $exact ?: $lookup[0];
            }
        }

        throw new RuntimeException("Series with TMDB ID {$tmdbId} was not found in Sonarr lookup.");
    }

    public function fetchInteractiveReleases(int $sonarrSeriesId): array
    {
        $releases = $this->client(90)->get('/release', [
            'seriesId' => $sonarrSeriesId,
        ])->throw()->json() ?? [];

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
                'fullSeason' => (bool) ($r['fullSeason'] ?? false),
                'seasonNumber' => $r['seasonNumber'] ?? null,
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

    public function grabRelease(int $sonarrSeriesId, string $guid, int $indexerId): array
    {
        $grabbed = $this->client(30)->post('/release', [
            'guid' => $guid,
            'indexerId' => $indexerId,
        ])->throw()->json();

        $series = $this->client()->get("/series/{$sonarrSeriesId}")->throw()->json();
        $series['monitored'] = true;
        $series['seasons'] = collect($series['seasons'] ?? [])
            ->map(function (array $season) {
                // Keep specials (0) unmonitored by default; monitor regular seasons.
                if ((int) ($season['seasonNumber'] ?? -1) === 0) {
                    return $season;
                }

                return array_merge($season, ['monitored' => true]);
            })
            ->values()
            ->all();

        $this->client()->put("/series/{$sonarrSeriesId}", $series)->throw();

        GhostMedia::query()
            ->where('media_type', 'tv')
            ->where('external_arr_id', $sonarrSeriesId)
            ->delete();

        return is_array($grabbed) ? $grabbed : ['ok' => true];
    }

    public function deleteGhostSeries(int $sonarrSeriesId): void
    {
        $ghost = GhostMedia::query()
            ->where('media_type', 'tv')
            ->where('external_arr_id', $sonarrSeriesId)
            ->first();

        if (! $ghost) {
            return;
        }

        $this->client()->delete("/series/{$sonarrSeriesId}", [
            'deleteFiles' => false,
            'addImportListExclusion' => false,
        ])->throw();

        $ghost->delete();
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

        $series = $this->listSeries();

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
