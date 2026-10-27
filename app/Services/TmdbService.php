<?php

namespace App\Services;

use App\Models\ServiceConfig;
use App\Services\Concerns\ResolvesServiceConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class TmdbService
{
    use ResolvesServiceConfig;

    public function __construct(private ?ServiceConfig $config = null) {}

    private function client(): PendingRequest
    {
        $config = $this->resolveConfig('tmdb', $this->config);
        $base = $this->normalizedBaseUrl($config) ?: 'https://api.themoviedb.org/3';

        return Http::baseUrl($base)
            ->withToken($config->api_key)
            ->acceptJson()
            ->timeout(20);
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

    public function trending(string $mediaType = 'all', string $window = 'week'): array
    {
        return $this->client()->get("/trending/{$mediaType}/{$window}")->throw()->json('results') ?? [];
    }

    public function popular(string $mediaType = 'movie'): array
    {
        return $this->client()->get("/{$mediaType}/popular")->throw()->json('results') ?? [];
    }

    public function search(string $query, string $mediaType = 'multi'): array
    {
        return $this->client()->get("/search/{$mediaType}", [
            'query' => $query,
            'include_adult' => false,
        ])->throw()->json('results') ?? [];
    }

    public function movie(int $tmdbId): array
    {
        return $this->client()->get("/movie/{$tmdbId}")->throw()->json();
    }

    public function tv(int $tmdbId): array
    {
        return $this->client()->get("/tv/{$tmdbId}")->throw()->json();
    }
}
