<?php

namespace App\Services;

use App\Models\Media;
use App\Support\MediaStatus;
use App\Support\Settings;
use Throwable;

class DiscoverEnricher
{
    public function __construct(private TelemetryAggregator $telemetry) {}

    /**
     * @param  list<array<string, mixed>>  $results
     * @return list<array<string, mixed>>
     */
    public function enrich(array $results, string $fallbackType = 'movie'): array
    {
        $lookup = [];
        foreach ($results as $item) {
            $type = $item['media_type'] ?? $fallbackType;
            if (! in_array($type, ['movie', 'tv'], true)) {
                continue;
            }
            $lookup[] = [
                'tmdbId' => (int) $item['id'],
                'mediaType' => $type,
            ];
        }

        $related = Media::getRelatedMedia($lookup);

        $downloading = [];
        try {
            $downloading = $this->telemetry->downloadingTmdbIds();
        } catch (Throwable) {
            $downloading = [];
        }

        $hideAvailable = (bool) (Settings::main()['hideAvailable'] ?? false);

        return collect($results)
            ->map(function (array $item) use ($fallbackType, $related, $downloading) {
                $type = $item['media_type'] ?? $fallbackType;
                if (! in_array($type, ['movie', 'tv'], true)) {
                    return null;
                }

                $tmdbId = (int) $item['id'];
                $key = $type.':'.$tmdbId;
                /** @var Media|null $media */
                $media = $related->get($key);
                $statusCode = $media ? (int) $media->status : MediaStatus::UNKNOWN;

                if (in_array($tmdbId, $downloading, true)) {
                    $statusCode = MediaStatus::PROCESSING;
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
                    'voteAverage' => isset($item['vote_average']) ? round((float) $item['vote_average'], 1) : null,
                    'mediaStatus' => $statusCode,
                    'libraryStatus' => MediaStatus::badge($statusCode),
                    'externalServiceId' => $media?->external_service_id,
                ];
            })
            ->filter()
            ->when($hideAvailable, fn ($c) => $c->reject(fn (array $i) => $i['libraryStatus'] === 'AVAILABLE'))
            ->values()
            ->all();
    }
}
