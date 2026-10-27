<?php

namespace App\Services;

use App\Models\ServiceConfig;

class TelemetryAggregator
{
    public function __construct(
        private RadarrService $radarr,
        private SonarrService $sonarr,
        private QbitService $qbit
    ) {}

    public function getUnifiedQueue(): array
    {
        $arrItems = collect();

        if (ServiceConfig::for('radarr')?->is_enabled) {
            foreach ($this->radarr->getQueue() as $item) {
                $key = strtoupper((string) ($item['downloadId'] ?? ''));
                if ($key !== '') {
                    $arrItems[$key] = $this->normalizeArrItem($item, 'movie');
                }
            }
        }

        if (ServiceConfig::for('sonarr')?->is_enabled) {
            foreach ($this->sonarr->getQueue() as $item) {
                $key = strtoupper((string) ($item['downloadId'] ?? ''));
                if ($key !== '') {
                    $arrItems[$key] = $this->normalizeArrItem($item, 'series');
                }
            }
        }

        if (! ServiceConfig::for('qbittorrent')?->is_enabled) {
            return [];
        }

        return collect($this->qbit->getTorrents())->map(function (array $t) use ($arrItems) {
            $hashKey = strtoupper($t['hash'] ?? '');
            $arrItem = $arrItems->get($hashKey);

            return [
                'hash' => $t['hash'] ?? null,
                'rawName' => $t['name'] ?? null,
                'cleanTitle' => $arrItem['title'] ?? ($t['name'] ?? 'Unknown'),
                'year' => $arrItem['year'] ?? null,
                'tmdbId' => $arrItem['tmdbId'] ?? null,
                'mediaType' => $arrItem['mediaType'] ?? null,
                'posterUrl' => $arrItem['posterUrl'] ?? null,
                'releaseGroup' => $arrItem['releaseGroup'] ?? null,
                'category' => ($t['category'] ?? '') !== '' ? $t['category'] : 'Uncategorized',
                'progress' => round(($t['progress'] ?? 0) * 100, 1),
                'dlSpeed' => $t['dlspeed'] ?? 0,
                'dlSpeedHuman' => $this->formatSpeed((int) ($t['dlspeed'] ?? 0)),
                'upSpeedHuman' => $this->formatSpeed((int) ($t['upspeed'] ?? 0)),
                'etaSeconds' => $t['eta'] ?? 0,
                'state' => $t['state'] ?? 'unknown',
                'arrStatus' => $arrItem['trackedDownloadState'] ?? 'untracked',
                'statusMessages' => $arrItem['statusMessages'] ?? [],
                'seeds' => $t['num_seeds'] ?? 0,
                'peers' => $t['num_leechs'] ?? 0,
                'sizeHuman' => round(($t['size'] ?? 0) / 1073741824, 2).' GB',
                'ratio' => round($t['ratio'] ?? 0, 2),
            ];
        })->values()->all();
    }

    public function downloadingTmdbIds(): array
    {
        return collect($this->getUnifiedQueue())
            ->filter(fn ($item) => ($item['progress'] ?? 100) < 100)
            ->pluck('tmdbId')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeArrItem(array $item, string $mediaType): array
    {
        $entity = $mediaType === 'movie'
            ? ($item['movie'] ?? [])
            : ($item['series'] ?? []);

        $posterUrl = null;
        if (! empty($entity['images']) && $mediaType === 'movie') {
            $posterUrl = $this->radarr->posterUrl($entity['images']);
        }

        return [
            'mediaType' => $mediaType,
            'title' => $entity['title'] ?? null,
            'year' => $entity['year'] ?? null,
            'tmdbId' => $entity['tmdbId'] ?? null,
            'posterUrl' => $posterUrl,
            'releaseGroup' => $item['downloadClient'] ?? ($item['indexer'] ?? null),
            'trackedDownloadState' => $item['trackedDownloadState'] ?? null,
            'statusMessages' => $item['statusMessages'] ?? [],
        ];
    }

    private function formatSpeed(int $bytesPerSec): string
    {
        if ($bytesPerSec <= 0) {
            return '0 B/s';
        }

        if ($bytesPerSec < 1048576) {
            return round($bytesPerSec / 1024, 1).' KB/s';
        }

        return round($bytesPerSec / 1048576, 2).' MB/s';
    }
}
