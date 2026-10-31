<?php

namespace App\Support;

/**
 * Curated TMDB watch-provider IDs for discover filters.
 * IDs are mostly global; availability still depends on watch_region.
 */
final class WatchProviders
{
    /**
     * @return list<array{id: int, name: string, slug: string}>
     */
    public static function curated(): array
    {
        return [
            ['id' => 8, 'name' => 'Netflix', 'slug' => 'netflix'],
            ['id' => 337, 'name' => 'Disney+', 'slug' => 'disney'],
            ['id' => 1899, 'name' => 'Max', 'slug' => 'max'],
            ['id' => 9, 'name' => 'Prime Video', 'slug' => 'prime'],
            ['id' => 350, 'name' => 'Apple TV+', 'slug' => 'apple'],
            ['id' => 531, 'name' => 'Paramount+', 'slug' => 'paramount'],
            ['id' => 15, 'name' => 'Hulu', 'slug' => 'hulu'],
            ['id' => 386, 'name' => 'Peacock', 'slug' => 'peacock'],
            ['id' => 283, 'name' => 'Crunchyroll', 'slug' => 'crunchyroll'],
            ['id' => 1773, 'name' => 'SkyShowtime', 'slug' => 'skyshowtime'],
            ['id' => 29, 'name' => 'Sky Go', 'slug' => 'skygo'],
            ['id' => 257, 'name' => 'fuboTV', 'slug' => 'fubo'],
        ];
    }

    public static function find(int $id): ?array
    {
        return collect(self::curated())->firstWhere('id', $id);
    }
}
