<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\ServiceConfig;
use App\Services\DiscoverEnricher;
use App\Services\RadarrService;
use App\Services\TmdbService;
use App\Support\DiscoverCatalog;
use App\Support\MediaStatus;
use App\Support\Settings;
use App\Support\WatchProviders;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DiscoverController extends Controller
{
    private const SORT_OPTIONS = [
        'popularity.desc' => 'Popularity Descending',
        'popularity.asc' => 'Popularity Ascending',
        'vote_average.desc' => 'Rating Descending',
        'vote_average.asc' => 'Rating Ascending',
        'primary_release_date.desc' => 'Release Date Descending',
        'primary_release_date.asc' => 'Release Date Ascending',
        'title.asc' => 'Title (A–Z)',
        'title.desc' => 'Title (Z–A)',
    ];

    public function index(
        Request $request,
        TmdbService $tmdb,
        RadarrService $radarr,
        DiscoverEnricher $enricher
    ): Response {
        $query = trim((string) $request->get('q', ''));
        $typeParam = $request->get('type');
        $mediaType = in_array($typeParam, ['movie', 'tv'], true) ? $typeParam : 'all';
        $providerId = $request->filled('provider') ? (int) $request->get('provider') : null;
        $genreId = $request->filled('genre') ? (int) $request->get('genre') : null;
        $studioId = $request->filled('studio') ? (int) $request->get('studio') : null;
        $sortBy = (string) $request->get('sort', 'popularity.desc');
        if (! array_key_exists($sortBy, self::SORT_OPTIONS)) {
            $sortBy = 'popularity.desc';
        }
        $region = strtoupper((string) (Settings::main()['discoverRegion'] ?? 'CZ'));

        $sections = [];
        $items = [];
        $mode = 'home';
        $pageTitle = 'Discover';
        $activeFilters = 0;

        if ($genreId) {
            $activeFilters++;
        }
        if ($studioId) {
            $activeFilters++;
        }
        if ($providerId) {
            $activeFilters++;
        }

        // List views: Movies, Series, genre/studio/network/search — dense Seerr grid.
        $isList = $query !== ''
            || $genreId
            || $studioId
            || $providerId
            || in_array($mediaType, ['movie', 'tv'], true);

        try {
            if ($isList) {
                [$mode, $pageTitle, $items] = $this->listView(
                    $tmdb,
                    $enricher,
                    $query,
                    $mediaType,
                    $genreId,
                    $studioId,
                    $providerId,
                    $sortBy,
                    $region
                );
            } else {
                $sections = $this->homeSections($tmdb, $enricher, $region);
            }
        } catch (Throwable) {
            $sections = [];
            $items = [];
        }

        $providers = $this->providersForUi($tmdb, $mediaType === 'tv' ? 'tv' : 'movie', $region);

        $radarrDefaults = ServiceConfig::for('radarr')?->defaults ?? [];
        $radarrMeta = ['profiles' => [], 'folders' => []];

        try {
            if (ServiceConfig::for('radarr')?->is_enabled) {
                $radarrMeta = $radarr->getMetadataProfiles();
            }
        } catch (Throwable) {
            // Keep empty metadata.
        }

        return Inertia::render('Discover/Index', [
            'mode' => $mode,
            'pageTitle' => $pageTitle,
            'sections' => $sections,
            'items' => $items,
            'providers' => $providers,
            'movieGenres' => DiscoverCatalog::movieGenres(),
            'tvGenres' => DiscoverCatalog::tvGenres(),
            'studios' => DiscoverCatalog::studios(),
            'sortOptions' => self::SORT_OPTIONS,
            'activeFilters' => $activeFilters,
            'filters' => [
                'q' => $query,
                'type' => $mediaType === 'all' ? null : $mediaType,
                'provider' => $providerId,
                'genre' => $genreId,
                'studio' => $studioId,
                'sort' => $sortBy,
                'region' => $region,
            ],
            'radarr' => [
                'defaults' => $radarrDefaults,
                'profiles' => $radarrMeta['profiles'] ?? [],
                'folders' => $radarrMeta['folders'] ?? [],
            ],
        ]);
    }

    /**
     * @return array{0: string, 1: string, 2: list<array<string, mixed>>}
     */
    private function listView(
        TmdbService $tmdb,
        DiscoverEnricher $enricher,
        string $query,
        string $mediaType,
        ?int $genreId,
        ?int $studioId,
        ?int $providerId,
        string $sortBy,
        string $region
    ): array {
        $type = $mediaType === 'tv' ? 'tv' : 'movie';
        // TMDB uses different date sort keys for TV.
        $sort = $sortBy;
        if ($type === 'tv') {
            $sort = str_replace('primary_release_date', 'first_air_date', $sort);
        }

        if ($query !== '') {
            $raw = $tmdb->search($query, $mediaType === 'all' ? 'multi' : $type, pages: 3);

            return ['list', "Results for “{$query}”", $enricher->enrich($raw, $type)];
        }

        if ($studioId) {
            $studio = collect(DiscoverCatalog::studios())->firstWhere('id', $studioId);
            $raw = $tmdb->discover('movie', [
                'with_companies' => (string) $studioId,
                'sort_by' => str_replace('first_air_date', 'primary_release_date', $sort),
                'region' => $region,
            ], pages: 3);

            return ['list', $studio['name'] ?? 'Studio', $enricher->enrich($this->tag($raw, 'movie'), 'movie')];
        }

        if ($providerId) {
            $provider = WatchProviders::find($providerId);
            $raw = $tmdb->discover($type, [
                'watch_region' => $region,
                'with_watch_providers' => (string) $providerId,
                'with_watch_monetization_types' => 'flatrate',
                'sort_by' => $sort,
                'region' => $region,
            ], pages: 3);

            return ['list', $provider['name'] ?? 'Network', $enricher->enrich($this->tag($raw, $type), $type)];
        }

        if ($genreId) {
            $genre = collect(
                $type === 'tv' ? DiscoverCatalog::tvGenres() : DiscoverCatalog::movieGenres()
            )->firstWhere('id', $genreId);
            $raw = $tmdb->discover($type, [
                'with_genres' => (string) $genreId,
                'sort_by' => $sort,
                'region' => $region,
            ], pages: 3);

            return ['list', $genre['name'] ?? 'Genre', $enricher->enrich($this->tag($raw, $type), $type)];
        }

        // Movies / Series browse
        $raw = $tmdb->discover($type, [
            'sort_by' => $sort,
            'region' => $region,
            'vote_count.gte' => 50,
        ], pages: 3);

        return [
            'list',
            $type === 'tv' ? 'Series' : 'Movies',
            $enricher->enrich($this->tag($raw, $type), $type),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $raw
     * @return list<array<string, mixed>>
     */
    private function tag(array $raw, string $mediaType): array
    {
        return array_map(
            fn ($r) => array_merge($r, ['media_type' => $r['media_type'] ?? $mediaType]),
            $raw
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function homeSections(TmdbService $tmdb, DiscoverEnricher $enricher, string $region): array
    {
        $sections = [];

        $recent = $this->recentlyAdded();
        if ($recent !== []) {
            $sections[] = [
                'key' => 'recently_added',
                'title' => 'Recently Added',
                'kind' => 'media',
                'items' => $recent,
            ];
        }

        try {
            $sections[] = [
                'key' => 'trending',
                'title' => 'Trending',
                'kind' => 'media',
                'href' => '/discover?type=movie',
                'items' => $enricher->enrich($tmdb->trending('all', 'week', pages: 2), 'movie'),
            ];
        } catch (Throwable) {
        }

        try {
            $sections[] = [
                'key' => 'popular_movies',
                'title' => 'Popular Movies',
                'kind' => 'media',
                'href' => '/discover?type=movie',
                'items' => $enricher->enrich($this->tag($tmdb->popular('movie', pages: 2), 'movie'), 'movie'),
            ];
        } catch (Throwable) {
        }

        $sections[] = [
            'key' => 'movie_genres',
            'title' => 'Movie Genres',
            'kind' => 'genres',
            'items' => DiscoverCatalog::movieGenres(),
            'mediaType' => 'movie',
        ];

        try {
            $sections[] = [
                'key' => 'upcoming_movies',
                'title' => 'Upcoming Movies',
                'kind' => 'media',
                'href' => '/discover?type=movie&sort=primary_release_date.desc',
                'items' => $enricher->enrich($this->tag($tmdb->upcoming(pages: 1), 'movie'), 'movie'),
            ];
        } catch (Throwable) {
        }

        $sections[] = [
            'key' => 'studios',
            'title' => 'Studios',
            'kind' => 'studios',
            'items' => DiscoverCatalog::studios(),
        ];

        try {
            $sections[] = [
                'key' => 'popular_series',
                'title' => 'Popular Series',
                'kind' => 'media',
                'href' => '/discover?type=tv',
                'items' => $enricher->enrich($this->tag($tmdb->popular('tv', pages: 2), 'tv'), 'tv'),
            ];
        } catch (Throwable) {
        }

        $sections[] = [
            'key' => 'tv_genres',
            'title' => 'Series Genres',
            'kind' => 'genres',
            'items' => DiscoverCatalog::tvGenres(),
            'mediaType' => 'tv',
        ];

        try {
            $sections[] = [
                'key' => 'upcoming_series',
                'title' => 'Upcoming Series',
                'kind' => 'media',
                'href' => '/discover?type=tv',
                'items' => $enricher->enrich($this->tag($tmdb->onTheAir(pages: 1), 'tv'), 'tv'),
            ];
        } catch (Throwable) {
        }

        $networks = $this->providersForUi($tmdb, 'movie', $region);
        if ($networks !== []) {
            $sections[] = [
                'key' => 'networks',
                'title' => 'Networks',
                'kind' => 'networks',
                'items' => $networks,
            ];
        }

        return array_values(array_filter(
            $sections,
            function (array $section) {
                if (($section['kind'] ?? 'media') !== 'media') {
                    return true;
                }

                return count($section['items'] ?? []) > 0;
            }
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentlyAdded(): array
    {
        return Media::query()
            ->whereIn('status', [
                MediaStatus::AVAILABLE,
                MediaStatus::PARTIALLY_AVAILABLE,
                MediaStatus::PROCESSING,
                MediaStatus::PENDING,
            ])
            ->orderByDesc('media_added_at')
            ->orderByDesc('updated_at')
            ->limit(24)
            ->get()
            ->map(function (Media $media) {
                $poster = $media->poster_path;
                if ($poster && ! str_starts_with($poster, 'http')) {
                    $poster = 'https://image.tmdb.org/t/p/w342'.$poster;
                }

                return [
                    'tmdbId' => (int) $media->tmdb_id,
                    'mediaType' => $media->media_type,
                    'title' => $media->title ?: 'Untitled',
                    'year' => $media->year,
                    'overview' => '',
                    'posterUrl' => $poster,
                    'backdropUrl' => null,
                    'voteAverage' => null,
                    'mediaStatus' => (int) $media->status,
                    'libraryStatus' => MediaStatus::badge((int) $media->status),
                    'externalServiceId' => $media->external_service_id,
                ];
            })
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, slug: string, logoUrl: ?string, available: bool}>
     */
    private function providersForUi(TmdbService $tmdb, string $mediaType, string $region): array
    {
        try {
            $remote = collect($tmdb->watchProviders($mediaType, $region))
                ->keyBy(fn (array $p) => (int) $p['provider_id']);
        } catch (Throwable) {
            $remote = collect();
        }

        return collect(WatchProviders::curated())
            ->map(function (array $provider) use ($remote) {
                $match = $remote->get($provider['id']);
                $logoPath = $match['logo_path'] ?? null;

                return [
                    'id' => $provider['id'],
                    'name' => $provider['name'],
                    'slug' => $provider['slug'],
                    'logoUrl' => $logoPath ? 'https://image.tmdb.org/t/p/w92'.$logoPath : null,
                    'available' => true,
                ];
            })
            ->values()
            ->all();
    }
}
