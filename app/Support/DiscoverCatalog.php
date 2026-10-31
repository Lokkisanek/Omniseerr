<?php

namespace App\Support;

/**
 * Seerr-style discover catalog metadata (genres, studios, network tiles).
 */
final class DiscoverCatalog
{
    /**
     * @return list<array{id: int, name: string, color: string}>
     */
    public static function movieGenres(): array
    {
        return [
            ['id' => 28, 'name' => 'Action', 'color' => '#991b1b'],
            ['id' => 12, 'name' => 'Adventure', 'color' => '#5b21b6'],
            ['id' => 16, 'name' => 'Animation', 'color' => '#0f766e'],
            ['id' => 35, 'name' => 'Comedy', 'color' => '#a16207'],
            ['id' => 80, 'name' => 'Crime', 'color' => '#1d4ed8'],
            ['id' => 99, 'name' => 'Documentary', 'color' => '#065f46'],
            ['id' => 18, 'name' => 'Drama', 'color' => '#9f1239'],
            ['id' => 14, 'name' => 'Fantasy', 'color' => '#6d28d9'],
            ['id' => 27, 'name' => 'Horror', 'color' => '#7f1d1d'],
            ['id' => 878, 'name' => 'Science Fiction', 'color' => '#1e3a8a'],
            ['id' => 53, 'name' => 'Thriller', 'color' => '#374151'],
            ['id' => 10752, 'name' => 'War', 'color' => '#44403c'],
        ];
    }

    /**
     * @return list<array{id: int, name: string, color: string}>
     */
    public static function tvGenres(): array
    {
        return [
            ['id' => 10759, 'name' => 'Action & Adventure', 'color' => '#5b21b6'],
            ['id' => 16, 'name' => 'Animation', 'color' => '#0f766e'],
            ['id' => 35, 'name' => 'Comedy', 'color' => '#a16207'],
            ['id' => 80, 'name' => 'Crime', 'color' => '#1d4ed8'],
            ['id' => 99, 'name' => 'Documentary', 'color' => '#065f46'],
            ['id' => 18, 'name' => 'Drama', 'color' => '#9f1239'],
            ['id' => 10765, 'name' => 'Sci-Fi & Fantasy', 'color' => '#1e3a8a'],
            ['id' => 10751, 'name' => 'Family', 'color' => '#0369a1'],
            ['id' => 9648, 'name' => 'Mystery', 'color' => '#4c1d95'],
            ['id' => 10768, 'name' => 'War & Politics', 'color' => '#44403c'],
        ];
    }

    /**
     * TMDB production company IDs.
     *
     * @return list<array{id: int, name: string, image: string}>
     */
    public static function studios(): array
    {
        return [
            ['id' => 2, 'name' => 'Walt Disney Pictures', 'image' => '/studios/disney.svg'],
            ['id' => 25, 'name' => '20th Century Studios', 'image' => '/studios/20th.svg'],
            ['id' => 34, 'name' => 'Sony Pictures', 'image' => '/studios/sony.svg'],
            ['id' => 174, 'name' => 'Warner Bros. Pictures', 'image' => '/studios/warner.svg'],
            ['id' => 33, 'name' => 'Universal Pictures', 'image' => '/studios/universal.svg'],
            ['id' => 4, 'name' => 'Paramount', 'image' => '/studios/paramount.svg'],
            ['id' => 3, 'name' => 'Pixar', 'image' => '/studios/pixar.svg'],
            ['id' => 420, 'name' => 'Marvel Studios', 'image' => '/studios/marvel.svg'],
        ];
    }
}
