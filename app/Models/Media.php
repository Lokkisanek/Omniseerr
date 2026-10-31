<?php

namespace App\Models;

use App\Support\MediaStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Media extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'media_type',
        'tmdb_id',
        'tvdb_id',
        'imdb_id',
        'status',
        'status_4k',
        'external_service_id',
        'external_service_id_4k',
        'external_service_slug',
        'external_service_slug_4k',
        'title',
        'year',
        'poster_path',
        'media_added_at',
    ];

    protected function casts(): array
    {
        return [
            'tmdb_id' => 'integer',
            'tvdb_id' => 'integer',
            'status' => 'integer',
            'status_4k' => 'integer',
            'external_service_id' => 'integer',
            'external_service_id_4k' => 'integer',
            'year' => 'integer',
            'media_added_at' => 'datetime',
        ];
    }

    public function badgeStatus(): string
    {
        return MediaStatus::badge((int) $this->status);
    }

    /**
     * Seerr-style related media lookup for a discover page of TMDB results.
     *
     * @param  array<int, array{tmdbId: int, mediaType: string}>  $items
     * @return Collection<int, self>
     */
    public static function getRelatedMedia(array $items): Collection
    {
        if ($items === []) {
            return collect();
        }

        $ids = collect($items)->pluck('tmdbId')->unique()->values()->all();

        return static::query()
            ->whereIn('tmdb_id', $ids)
            ->get()
            ->filter(function (self $media) use ($items) {
                return collect($items)->contains(
                    fn (array $item) => (int) $item['tmdbId'] === (int) $media->tmdb_id
                        && $item['mediaType'] === $media->media_type
                );
            })
            ->keyBy(fn (self $media) => $media->media_type.':'.$media->tmdb_id);
    }

    public static function upsertFromArr(array $attributes): self
    {
        return static::query()->updateOrCreate(
            [
                'media_type' => $attributes['media_type'],
                'tmdb_id' => $attributes['tmdb_id'],
            ],
            $attributes
        );
    }
}
