<?php

namespace App\Http\Controllers;

use App\Services\DiscoverEnricher;
use App\Services\TmdbService;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PersonController extends Controller
{
    public function show(int $personId, TmdbService $tmdb, DiscoverEnricher $enricher): Response
    {
        try {
            $raw = $tmdb->personDetails($personId);
        } catch (Throwable) {
            abort(404, 'Person not found.');
        }

        $credits = $raw['combined_credits']['cast'] ?? [];

        $movies = collect($credits)
            ->filter(fn (array $c) => ($c['media_type'] ?? '') === 'movie')
            ->sortByDesc(fn (array $c) => $c['popularity'] ?? 0)
            ->values()
            ->take(40)
            ->all();

        $series = collect($credits)
            ->filter(fn (array $c) => ($c['media_type'] ?? '') === 'tv')
            ->sortByDesc(fn (array $c) => $c['popularity'] ?? 0)
            ->values()
            ->take(40)
            ->all();

        return Inertia::render('Person/Show', [
            'person' => [
                'id' => (int) $raw['id'],
                'name' => $raw['name'] ?? '',
                'biography' => $raw['biography'] ?? '',
                'birthday' => $raw['birthday'] ?? null,
                'placeOfBirth' => $raw['place_of_birth'] ?? null,
                'knownFor' => $raw['known_for_department'] ?? null,
                'profileUrl' => ! empty($raw['profile_path'])
                    ? 'https://image.tmdb.org/t/p/w500'.$raw['profile_path']
                    : null,
                'imdbId' => $raw['external_ids']['imdb_id'] ?? null,
            ],
            'movies' => $enricher->enrich($movies, 'movie'),
            'series' => $enricher->enrich($series, 'tv'),
        ]);
    }
}
