<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\ServiceConfig;
use App\Services\DiscoverEnricher;
use App\Services\RadarrService;
use App\Services\SonarrService;
use App\Services\TmdbService;
use App\Support\MediaStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MediaDetailController extends Controller
{
    public function movie(
        int $tmdbId,
        TmdbService $tmdb,
        DiscoverEnricher $enricher,
        RadarrService $radarr,
        SonarrService $sonarr
    ): Response {
        return $this->renderDetail('movie', $tmdbId, $tmdb, $enricher, $radarr, $sonarr);
    }

    public function tv(
        int $tmdbId,
        TmdbService $tmdb,
        DiscoverEnricher $enricher,
        RadarrService $radarr,
        SonarrService $sonarr
    ): Response {
        return $this->renderDetail('tv', $tmdbId, $tmdb, $enricher, $radarr, $sonarr);
    }

    private function renderDetail(
        string $mediaType,
        int $tmdbId,
        TmdbService $tmdb,
        DiscoverEnricher $enricher,
        RadarrService $radarr,
        SonarrService $sonarr
    ): Response {
        try {
            $raw = $mediaType === 'tv'
                ? $tmdb->tvDetails($tmdbId)
                : $tmdb->movieDetails($tmdbId);
        } catch (Throwable $e) {
            abort(404, 'Title not found.');
        }

        $media = Media::query()
            ->where('media_type', $mediaType)
            ->where('tmdb_id', $tmdbId)
            ->first();

        $status = $media ? (int) $media->status : MediaStatus::UNKNOWN;

        $detail = $mediaType === 'tv'
            ? $this->mapTv($raw, $status)
            : $this->mapMovie($raw, $status);

        $recommendations = $enricher->enrich(
            array_map(
                fn ($r) => array_merge($r, ['media_type' => $mediaType]),
                $raw['recommendations']['results'] ?? []
            ),
            $mediaType
        );

        $similar = $enricher->enrich(
            array_map(
                fn ($r) => array_merge($r, ['media_type' => $mediaType]),
                $raw['similar']['results'] ?? []
            ),
            $mediaType
        );

        $arrDefaults = [];
        $arrMeta = ['profiles' => [], 'folders' => []];

        try {
            if ($mediaType === 'movie' && ServiceConfig::for('radarr')?->is_enabled) {
                $arrDefaults = ServiceConfig::for('radarr')?->defaults ?? [];
                $arrMeta = $radarr->getMetadataProfiles();
            }
            if ($mediaType === 'tv' && ServiceConfig::for('sonarr')?->is_enabled) {
                $arrDefaults = ServiceConfig::for('sonarr')?->defaults ?? [];
                $arrMeta = $sonarr->getMetadataProfiles();
            }
        } catch (Throwable) {
        }

        return Inertia::render('Media/Show', [
            'mediaType' => $mediaType,
            'detail' => $detail,
            'recommendations' => array_slice($recommendations, 0, 20),
            'similar' => array_slice($similar, 0, 20),
            'sonarrEnabled' => (bool) ServiceConfig::for('sonarr')?->is_enabled,
            'radarr' => [
                'defaults' => $arrDefaults,
                'profiles' => $arrMeta['profiles'] ?? [],
                'folders' => $arrMeta['folders'] ?? [],
            ],
        ]);
    }

    private function mapMovie(array $raw, int $status): array
    {
        $credits = $raw['credits'] ?? [];
        $cast = collect($credits['cast'] ?? [])
            ->take(20)
            ->map(fn (array $c) => [
                'id' => (int) $c['id'],
                'name' => $c['name'] ?? '',
                'character' => $c['character'] ?? '',
                'profileUrl' => ! empty($c['profile_path'])
                    ? 'https://image.tmdb.org/t/p/w185'.$c['profile_path']
                    : null,
            ])
            ->values()
            ->all();

        $crewJobs = ['Director', 'Writer', 'Screenplay', 'Producer', 'Editor', 'Director of Photography'];
        $crew = collect($credits['crew'] ?? [])
            ->filter(fn (array $c) => in_array($c['job'] ?? '', $crewJobs, true))
            ->unique(fn (array $c) => ($c['id'] ?? 0).'-'.($c['job'] ?? ''))
            ->take(12)
            ->map(fn (array $c) => [
                'id' => (int) $c['id'],
                'name' => $c['name'] ?? '',
                'job' => $c['job'] ?? '',
            ])
            ->values()
            ->all();

        $trailer = collect($raw['videos']['results'] ?? [])
            ->first(fn (array $v) => ($v['site'] ?? '') === 'YouTube' && ($v['type'] ?? '') === 'Trailer');

        $keywords = collect($raw['keywords']['keywords'] ?? $raw['keywords']['results'] ?? [])
            ->take(16)
            ->map(fn (array $k) => [
                'id' => (int) ($k['id'] ?? 0),
                'name' => (string) ($k['name'] ?? ''),
            ])
            ->filter(fn (array $k) => $k['id'] > 0 && $k['name'] !== '')
            ->values()
            ->all();

        return [
            'tmdbId' => (int) $raw['id'],
            'mediaType' => 'movie',
            'title' => $raw['title'] ?? 'Untitled',
            'tagline' => $raw['tagline'] ?? '',
            'overview' => $raw['overview'] ?? '',
            'year' => substr((string) ($raw['release_date'] ?? ''), 0, 4) ?: null,
            'releaseDate' => $raw['release_date'] ?? null,
            'runtime' => $raw['runtime'] ?? null,
            'status' => $raw['status'] ?? null,
            'voteAverage' => isset($raw['vote_average']) ? round((float) $raw['vote_average'], 1) : null,
            'genres' => collect($raw['genres'] ?? [])->pluck('name')->all(),
            'posterUrl' => ! empty($raw['poster_path'])
                ? 'https://image.tmdb.org/t/p/w500'.$raw['poster_path']
                : null,
            'backdropUrl' => ! empty($raw['backdrop_path'])
                ? 'https://image.tmdb.org/t/p/w1280'.$raw['backdrop_path']
                : null,
            'libraryStatus' => MediaStatus::badge($status),
            'mediaStatus' => $status,
            'budget' => $raw['budget'] ?? 0,
            'revenue' => $raw['revenue'] ?? 0,
            'originalLanguage' => $raw['original_language'] ?? null,
            'countries' => collect($raw['production_countries'] ?? [])->pluck('name')->all(),
            'studios' => collect($raw['production_companies'] ?? [])->take(6)->map(fn ($c) => [
                'id' => (int) ($c['id'] ?? 0),
                'name' => $c['name'] ?? '',
            ])->values()->all(),
            'imdbId' => $raw['external_ids']['imdb_id'] ?? $raw['imdb_id'] ?? null,
            'trailerKey' => $trailer['key'] ?? null,
            'cast' => $cast,
            'crew' => $crew,
            'keywords' => $keywords,
        ];
    }

    private function mapTv(array $raw, int $status): array
    {
        $credits = $raw['aggregate_credits'] ?? [];
        $cast = collect($credits['cast'] ?? [])
            ->take(20)
            ->map(function (array $c) {
                $roles = $c['roles'] ?? [];
                $character = $roles[0]['character'] ?? ($c['character'] ?? '');

                return [
                    'id' => (int) $c['id'],
                    'name' => $c['name'] ?? '',
                    'character' => $character,
                    'profileUrl' => ! empty($c['profile_path'])
                        ? 'https://image.tmdb.org/t/p/w185'.$c['profile_path']
                        : null,
                ];
            })
            ->values()
            ->all();

        $crew = collect($credits['crew'] ?? [])
            ->filter(function (array $c) {
                $jobs = collect($c['jobs'] ?? [])->pluck('job')->all();

                return (bool) array_intersect($jobs, ['Director', 'Writer', 'Executive Producer', 'Creator', 'Producer']);
            })
            ->take(12)
            ->map(function (array $c) {
                $job = collect($c['jobs'] ?? [])->pluck('job')->first() ?? 'Crew';

                return [
                    'id' => (int) $c['id'],
                    'name' => $c['name'] ?? '',
                    'job' => $job,
                ];
            })
            ->values()
            ->all();

        $trailer = collect($raw['videos']['results'] ?? [])
            ->first(fn (array $v) => ($v['site'] ?? '') === 'YouTube' && ($v['type'] ?? '') === 'Trailer');

        $keywords = collect($raw['keywords']['results'] ?? [])
            ->take(16)
            ->map(fn (array $k) => [
                'id' => (int) ($k['id'] ?? 0),
                'name' => (string) ($k['name'] ?? ''),
            ])
            ->filter(fn (array $k) => $k['id'] > 0 && $k['name'] !== '')
            ->values()
            ->all();

        $runtime = collect($raw['episode_run_time'] ?? [])->first();

        return [
            'tmdbId' => (int) $raw['id'],
            'mediaType' => 'tv',
            'title' => $raw['name'] ?? 'Untitled',
            'tagline' => $raw['tagline'] ?? '',
            'overview' => $raw['overview'] ?? '',
            'year' => substr((string) ($raw['first_air_date'] ?? ''), 0, 4) ?: null,
            'releaseDate' => $raw['first_air_date'] ?? null,
            'runtime' => $runtime,
            'status' => $raw['status'] ?? null,
            'voteAverage' => isset($raw['vote_average']) ? round((float) $raw['vote_average'], 1) : null,
            'genres' => collect($raw['genres'] ?? [])->pluck('name')->all(),
            'posterUrl' => ! empty($raw['poster_path'])
                ? 'https://image.tmdb.org/t/p/w500'.$raw['poster_path']
                : null,
            'backdropUrl' => ! empty($raw['backdrop_path'])
                ? 'https://image.tmdb.org/t/p/w1280'.$raw['backdrop_path']
                : null,
            'libraryStatus' => MediaStatus::badge($status),
            'mediaStatus' => $status,
            'budget' => 0,
            'revenue' => 0,
            'originalLanguage' => $raw['original_language'] ?? null,
            'countries' => collect($raw['origin_country'] ?? [])->all(),
            'studios' => collect($raw['networks'] ?? [])->take(6)->map(fn ($c) => [
                'id' => (int) ($c['id'] ?? 0),
                'name' => $c['name'] ?? '',
            ])->values()->all(),
            'imdbId' => $raw['external_ids']['imdb_id'] ?? null,
            'trailerKey' => $trailer['key'] ?? null,
            'cast' => $cast,
            'crew' => $crew,
            'keywords' => $keywords,
            'seasons' => (int) ($raw['number_of_seasons'] ?? 0),
            'episodes' => (int) ($raw['number_of_episodes'] ?? 0),
        ];
    }
}
