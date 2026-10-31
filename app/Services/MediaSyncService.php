<?php

namespace App\Services;

use App\Models\Media;
use App\Models\ServiceConfig;
use App\Support\MediaStatus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sync Radarr/Sonarr library state into local Media rows (Seerr availability pattern).
 */
class MediaSyncService
{
    public function syncAll(): array
    {
        $movies = 0;
        $series = 0;

        try {
            if (ServiceConfig::for('radarr')?->is_enabled) {
                $movies = $this->syncRadarr();
            }
        } catch (Throwable $e) {
            Log::warning('Media sync Radarr failed: '.$e->getMessage());
        }

        try {
            if (ServiceConfig::for('sonarr')?->is_enabled) {
                $series = $this->syncSonarr();
            }
        } catch (Throwable $e) {
            Log::warning('Media sync Sonarr failed: '.$e->getMessage());
        }

        return ['movies' => $movies, 'series' => $series];
    }

    public function syncRadarr(?RadarrService $radarr = null): int
    {
        $radarr ??= new RadarrService;
        $movies = $radarr->listMovies();
        $count = 0;

        foreach ($movies as $movie) {
            $tmdbId = (int) ($movie['tmdbId'] ?? 0);
            if ($tmdbId <= 0) {
                continue;
            }

            Media::upsertFromArr([
                'media_type' => 'movie',
                'tmdb_id' => $tmdbId,
                'imdb_id' => $movie['imdbId'] ?? null,
                'status' => MediaStatus::fromArrMovie(
                    (bool) ($movie['hasFile'] ?? false),
                    (bool) ($movie['monitored'] ?? false)
                ),
                'external_service_id' => $movie['id'] ?? null,
                'external_service_slug' => $movie['titleSlug'] ?? null,
                'title' => $movie['title'] ?? null,
                'year' => $movie['year'] ?? null,
                'poster_path' => $this->posterPath($movie['images'] ?? []),
                'media_added_at' => ! empty($movie['added']) ? $movie['added'] : now(),
            ]);
            $count++;
        }

        return $count;
    }

    public function syncSonarr(?SonarrService $sonarr = null): int
    {
        $sonarr ??= new SonarrService;
        $seriesList = $sonarr->listSeries();
        $count = 0;

        foreach ($seriesList as $series) {
            $tmdbId = (int) ($series['tmdbId'] ?? 0);
            if ($tmdbId <= 0) {
                continue;
            }

            $stats = $series['statistics'] ?? [];
            $percent = (float) ($stats['percentOfEpisodes'] ?? 0);

            Media::upsertFromArr([
                'media_type' => 'tv',
                'tmdb_id' => $tmdbId,
                'tvdb_id' => $series['tvdbId'] ?? null,
                'imdb_id' => $series['imdbId'] ?? null,
                'status' => MediaStatus::fromArrSeries(
                    $percent,
                    (bool) ($series['monitored'] ?? false)
                ),
                'external_service_id' => $series['id'] ?? null,
                'external_service_slug' => $series['titleSlug'] ?? null,
                'title' => $series['title'] ?? null,
                'year' => $series['year'] ?? null,
                'poster_path' => $this->posterPath($series['images'] ?? []),
                'media_added_at' => ! empty($series['added']) ? $series['added'] : now(),
            ]);
            $count++;
        }

        return $count;
    }

    public function markMovieProcessing(int $tmdbId, ?int $externalServiceId = null, ?string $slug = null, ?string $title = null): Media
    {
        $media = Media::query()->firstOrNew([
            'media_type' => 'movie',
            'tmdb_id' => $tmdbId,
        ]);

        if ((int) $media->status !== MediaStatus::AVAILABLE) {
            $media->status = MediaStatus::PROCESSING;
        }

        if ($externalServiceId) {
            $media->external_service_id = $externalServiceId;
        }
        if ($slug) {
            $media->external_service_slug = $slug;
        }
        if ($title) {
            $media->title = $title;
        }

        $media->save();

        return $media;
    }

    private function posterPath(array $images): ?string
    {
        $poster = collect($images)->firstWhere('coverType', 'poster');

        return $poster['remoteUrl'] ?? $poster['url'] ?? null;
    }
}
