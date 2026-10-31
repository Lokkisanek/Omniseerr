<?php

namespace App\Http\Controllers;

use App\Services\MediaSyncService;
use App\Services\RadarrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class InteractiveGrabController extends Controller
{
    public function prepare(Request $request, RadarrService $radarr): JsonResponse
    {
        $data = $request->validate([
            'tmdb_id' => 'required|integer',
            'media_type' => 'required|in:movie',
            'quality_profile_id' => 'nullable|integer',
            'root_folder_path' => 'nullable|string',
        ]);

        try {
            set_time_limit(120);

            $movie = $radarr->ensureMovieExistsForInteractiveSearch(
                (int) $data['tmdb_id'],
                $data['quality_profile_id'] ?? null,
                $data['root_folder_path'] ?? null
            );

            $releases = $radarr->fetchInteractiveReleases((int) $movie['id']);

            return response()->json([
                'radarrMovieId' => $movie['id'],
                'tmdbId' => (int) $data['tmdb_id'],
                'wasGhostAdded' => ! ($movie['hasFile'] ?? false) && ! ($movie['monitored'] ?? false),
                'releases' => $releases,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function execute(Request $request, RadarrService $radarr, MediaSyncService $mediaSync): JsonResponse
    {
        $data = $request->validate([
            'radarr_movie_id' => 'required|integer',
            'guid' => 'required|string',
            'indexer_id' => 'required|integer',
            'tmdb_id' => 'nullable|integer',
        ]);

        try {
            $result = $radarr->grabRelease(
                (int) $data['radarr_movie_id'],
                $data['guid'],
                (int) $data['indexer_id']
            );

            if (! empty($data['tmdb_id'])) {
                $mediaSync->markMovieProcessing(
                    (int) $data['tmdb_id'],
                    (int) $data['radarr_movie_id']
                );
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

    public function cleanup(int $radarrMovieId, RadarrService $radarr): JsonResponse
    {
        try {
            $radarr->deleteGhostMovie($radarrMovieId);

            return response()->json(['ok' => true]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
