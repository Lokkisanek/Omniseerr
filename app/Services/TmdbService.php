<?php

namespace App\Services;

use App\Models\ServiceConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TmdbService
{
    public function __construct(private ?ServiceConfig $config = null) {}

    private function client(): PendingRequest
    {
        [$base, $key] = $this->credentials();

        return Http::baseUrl($base)
            ->withToken($key)
            ->acceptJson()
            ->timeout(20);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function credentials(): array
    {
        $config = $this->config ?? ServiceConfig::for('tmdb');
        $base = rtrim(
            (string) ($config?->base_url ?: config('omniseerr.tmdb_base_url')),
            '/'
        );
        $key = (string) ($config?->api_key ?: config('omniseerr.tmdb_api_key'));

        if ($key === '') {
            throw new RuntimeException('TMDB API key is missing. Set TMDB_API_KEY in the environment.');
        }

        return [$base ?: 'https://api.themoviedb.org/3', $key];
    }

    public static function testConnection(string $apiKey, ?string $baseUrl = null): array
    {
        $base = rtrim($baseUrl ?: 'https://api.themoviedb.org/3', '/');

        $response = Http::baseUrl($base)
            ->withToken($apiKey)
            ->acceptJson()
            ->timeout(10)
            ->get('/configuration');

        $response->throw();

        return [
            'ok' => true,
            'message' => 'TMDB connection successful.',
            'imageBase' => $response->json('images.secure_base_url'),
        ];
    }

    public function trending(string $mediaType = 'all', string $window = 'week', int $pages = 1): array
    {
        return $this->collectPages("/trending/{$mediaType}/{$window}", [], $pages);
    }

    public function popular(string $mediaType = 'movie', int $pages = 1): array
    {
        return $this->collectPages("/{$mediaType}/popular", [], $pages);
    }

    public function nowPlaying(int $pages = 1): array
    {
        return $this->collectPages('/movie/now_playing', [], $pages);
    }

    public function upcoming(int $pages = 1): array
    {
        return $this->collectPages('/movie/upcoming', [], $pages);
    }

    public function onTheAir(int $pages = 1): array
    {
        return $this->collectPages('/tv/on_the_air', [], $pages);
    }

    public function topRated(string $mediaType = 'movie', int $pages = 1): array
    {
        return $this->collectPages("/{$mediaType}/top_rated", [], $pages);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function discover(string $mediaType, array $filters = [], int $pages = 1): array
    {
        $endpoint = $mediaType === 'tv' ? '/discover/tv' : '/discover/movie';

        $hasProviders = filled($filters['with_watch_providers'] ?? null);

        $query = array_filter([
            'sort_by' => $filters['sort_by'] ?? 'popularity.desc',
            'include_adult' => false,
            'watch_region' => $filters['watch_region'] ?? null,
            'with_watch_providers' => $filters['with_watch_providers'] ?? null,
            'with_watch_monetization_types' => $hasProviders
                ? ($filters['with_watch_monetization_types'] ?? 'flatrate')
                : null,
            'with_genres' => $filters['with_genres'] ?? null,
            'with_companies' => $filters['with_companies'] ?? null,
            'vote_count.gte' => $filters['vote_count.gte'] ?? ($hasProviders || isset($filters['with_genres']) ? 20 : null),
            'language' => $filters['language'] ?? 'en-US',
            'region' => $filters['region'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        return $this->collectPages($endpoint, $query, $pages);
    }

    public function search(string $query, string $mediaType = 'multi', int $pages = 1): array
    {
        return $this->collectPages("/search/{$mediaType}", [
            'query' => $query,
            'include_adult' => false,
        ], $pages);
    }

    public function movie(int $tmdbId): array
    {
        return $this->client()->get("/movie/{$tmdbId}")->throw()->json();
    }

    public function tv(int $tmdbId): array
    {
        return $this->client()->get("/tv/{$tmdbId}")->throw()->json();
    }

    /**
     * @return list<array{provider_id: int, provider_name: string, logo_path: ?string}>
     */
    public function watchProviders(string $mediaType, string $region): array
    {
        $endpoint = $mediaType === 'tv'
            ? '/watch/providers/tv'
            : '/watch/providers/movie';

        $cacheKey = "tmdb.watch_providers.{$mediaType}.{$region}";

        return Cache::remember($cacheKey, 86400, function () use ($endpoint, $region) {
            $results = $this->client()->get($endpoint, [
                'watch_region' => $region,
                'language' => 'en-US',
            ])->throw()->json('results') ?? [];

            return collect($results)
                ->map(fn (array $p) => [
                    'provider_id' => (int) $p['provider_id'],
                    'provider_name' => (string) ($p['provider_name'] ?? ''),
                    'logo_path' => $p['logo_path'] ?? null,
                ])
                ->values()
                ->all();
        });
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function collectPages(string $endpoint, array $query, int $pages): array
    {
        $pages = max(1, min($pages, 3));
        $merged = [];
        $seen = [];

        for ($page = 1; $page <= $pages; $page++) {
            $response = $this->client()->get($endpoint, [...$query, 'page' => $page])->throw()->json();
            $results = $response['results'] ?? [];

            foreach ($results as $item) {
                $id = (int) ($item['id'] ?? 0);
                if ($id <= 0 || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $merged[] = $item;
            }

            $totalPages = (int) ($response['total_pages'] ?? 1);
            if ($page >= $totalPages) {
                break;
            }
        }

        return $merged;
    }
}
