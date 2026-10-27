<?php

namespace App\Http\Controllers;

use App\Models\ServiceConfig;
use App\Services\RadarrService;
use App\Services\SonarrService;
use App\Services\TelemetryAggregator;
use App\Services\TmdbService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DiscoverController extends Controller
{
    public function index(
        Request $request,
        TmdbService $tmdb,
        RadarrService $radarr,
        SonarrService $sonarr,
        TelemetryAggregator $telemetry
    ): Response {
        $query = trim((string) $request->get('q', ''));
        $mediaType = $request->get('type', 'movie');

        try {
            $results = $query !== ''
                ? $tmdb->search($query, $mediaType === 'tv' ? 'tv' : ($mediaType === 'movie' ? 'movie' : 'multi'))
                : ($mediaType === 'tv' ? $tmdb->trending('tv') : $tmdb->trending('movie'));
        } catch (Throwable) {
            $results = [];
        }

        $movieIds = [];
        $tvIds = [];

        foreach ($results as $item) {
            $type = $item['media_type'] ?? $mediaType;
            if ($type === 'movie') {
                $movieIds[] = (int) $item['id'];
            }
            if ($type === 'tv') {
                $tvIds[] = (int) $item['id'];
            }
        }

        $movieStates = [];
        $tvStates = [];

        try {
            if (ServiceConfig::for('radarr')?->is_enabled) {
                $movieStates = $radarr->getLibraryStatesByTmdbIds(array_unique($movieIds));
            }
        } catch (Throwable) {
            $movieStates = [];
        }

        try {
            if (ServiceConfig::for('sonarr')?->is_enabled) {
                $tvStates = $sonarr->getLibraryStatesByTmdbIds(array_unique($tvIds));
            }
        } catch (Throwable) {
            $tvStates = [];
        }

        $downloading = [];
        try {
            $downloading = $telemetry->downloadingTmdbIds();
        } catch (Throwable) {
            $downloading = [];
        }

        $items = collect($results)->map(function (array $item) use ($mediaType, $movieStates, $tvStates, $downloading) {
            $type = $item['media_type'] ?? $mediaType;
            $tmdbId = (int) $item['id'];
            $state = $type === 'tv'
                ? ($tvStates[$tmdbId]['status'] ?? 'UNREQUESTED')
                : ($movieStates[$tmdbId]['status'] ?? 'UNREQUESTED');

            if (in_array($tmdbId, $downloading, true)) {
                $state = 'DOWNLOADING';
            }

            return [
                'tmdbId' => $tmdbId,
                'mediaType' => $type,
                'title' => $item['title'] ?? $item['name'] ?? 'Untitled',
                'year' => substr((string) ($item['release_date'] ?? $item['first_air_date'] ?? ''), 0, 4) ?: null,
                'overview' => $item['overview'] ?? '',
                'posterUrl' => ! empty($item['poster_path'])
                    ? 'https://image.tmdb.org/t/p/w342'.$item['poster_path']
                    : null,
                'backdropUrl' => ! empty($item['backdrop_path'])
                    ? 'https://image.tmdb.org/t/p/w1280'.$item['backdrop_path']
                    : null,
                'voteAverage' => $item['vote_average'] ?? null,
                'libraryStatus' => $state,
            ];
        })->values()->all();

        $radarrDefaults = ServiceConfig::for('radarr')?->defaults ?? [];
        $radarrMeta = ['profiles' => [], 'folders' => []];

        try {
            if (ServiceConfig::for('radarr')?->is_enabled) {
                $radarrMeta = $radarr->getMetadataProfiles();
            }
        } catch (Throwable) {
            // Keep empty metadata; UI can still open with defaults missing.
        }

        return Inertia::render('Discover/Index', [
            'items' => $items,
            'filters' => [
                'q' => $query,
                'type' => $mediaType,
            ],
            'radarr' => [
                'defaults' => $radarrDefaults,
                'profiles' => $radarrMeta['profiles'] ?? [],
                'folders' => $radarrMeta['folders'] ?? [],
            ],
        ]);
    }
}
