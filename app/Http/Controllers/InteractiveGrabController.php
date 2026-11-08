<?php

namespace App\Http\Controllers;

use App\Models\ServiceConfig;
use App\Services\MediaSyncService;
use App\Services\RadarrService;
use App\Services\SonarrService;
use App\Services\TmdbService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class InteractiveGrabController extends Controller
{
    public function prepare(
        Request $request,
        RadarrService $radarr,
        SonarrService $sonarr,
        TmdbService $tmdb
    ): JsonResponse {
        $data = $request->validate([
            'tmdb_id' => 'required|integer',
            'media_type' => 'required|in:movie,tv',
            'quality_profile_id' => 'nullable|integer',
            'root_folder_path' => 'nullable|string',
        ]);

        try {
            set_time_limit(120);
            $mediaType = $data['media_type'];

            if ($mediaType === 'movie') {
                if (! ServiceConfig::for('radarr')?->is_enabled) {
                    return response()->json(['message' => 'Radarr is not configured.'], 422);
                }

                $movie = $radarr->ensureMovieExistsForInteractiveSearch(
                    (int) $data['tmdb_id'],
                    $data['quality_profile_id'] ?? null,
                    $data['root_folder_path'] ?? null
                );

                $releases = $radarr->fetchInteractiveReleases((int) $movie['id']);

                return response()->json([
                    'mediaType' => 'movie',
                    'arrId' => $movie['id'],
                    'radarrMovieId' => $movie['id'],
                    'tmdbId' => (int) $data['tmdb_id'],
                    'wasGhostAdded' => ! ($movie['hasFile'] ?? false) && ! ($movie['monitored'] ?? false),
                    'releases' => $releases,
                ]);
            }

            if (! ServiceConfig::for('sonarr')?->is_enabled) {
                return response()->json(['message' => 'Sonarr is not configured. Enable it in Settings.'], 422);
            }

            $tvdbId = null;
            try {
                $details = $tmdb->tvDetails((int) $data['tmdb_id']);
                $tvdbId = isset($details['external_ids']['tvdb_id'])
                    ? (int) $details['external_ids']['tvdb_id']
                    : null;
            } catch (Throwable) {
                $tvdbId = null;
            }

            $series = $sonarr->ensureSeriesExistsForInteractiveSearch(
                (int) $data['tmdb_id'],
                $tvdbId,
                $data['quality_profile_id'] ?? null,
                $data['root_folder_path'] ?? null
            );

            $releases = $sonarr->fetchInteractiveReleases((int) $series['id']);

            return response()->json([
                'mediaType' => 'tv',
                'arrId' => $series['id'],
                'sonarrSeriesId' => $series['id'],
                'tmdbId' => (int) $data['tmdb_id'],
                'wasGhostAdded' => ! ($series['monitored'] ?? false),
                'releases' => $releases,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function execute(
        Request $request,
        RadarrService $radarr,
        SonarrService $sonarr,
        MediaSyncService $mediaSync
    ): JsonResponse {
        $data = $request->validate([
            'media_type' => 'required|in:movie,tv',
            'arr_id' => 'required|integer',
            'guid' => 'required|string',
            'indexer_id' => 'required|integer',
            'tmdb_id' => 'nullable|integer',
            // Back-compat with older movie-only clients.
            'radarr_movie_id' => 'nullable|integer',
        ]);

        $arrId = (int) ($data['arr_id'] ?? $data['radarr_movie_id']);
        $mediaType = $data['media_type'];

        try {
            if ($mediaType === 'movie') {
                $result = $radarr->grabRelease(
                    $arrId,
                    $data['guid'],
                    (int) $data['indexer_id']
                );

                if (! empty($data['tmdb_id'])) {
                    $mediaSync->markMovieProcessing((int) $data['tmdb_id'], $arrId);
                }
            } else {
                $result = $sonarr->grabRelease(
                    $arrId,
                    $data['guid'],
                    (int) $data['indexer_id']
                );

                if (! empty($data['tmdb_id'])) {
                    $mediaSync->markSeriesProcessing((int) $data['tmdb_id'], $arrId);
                }
            }

            return response()->json([
                'ok' => true,
                'result' => $result,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function cleanup(
        Request $request,
        int $arrId,
        RadarrService $radarr,
        SonarrService $sonarr
    ): JsonResponse {
        $mediaType = $request->query('media_type', 'movie');

        try {
            if ($mediaType === 'tv') {
                $sonarr->deleteGhostSeries($arrId);
            } else {
                $radarr->deleteGhostMovie($arrId);
            }

            return response()->json(['ok' => true]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
