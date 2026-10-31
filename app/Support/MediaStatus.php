<?php

namespace App\Support;

/**
 * Seerr-compatible media availability status.
 *
 * @see https://github.com/seerr-team/seerr/blob/develop/server/constants/media.ts
 */
final class MediaStatus
{
    public const UNKNOWN = 1;

    public const PENDING = 2;

    public const PROCESSING = 3;

    public const PARTIALLY_AVAILABLE = 4;

    public const AVAILABLE = 5;

    public const BLOCKLISTED = 6;

    public const DELETED = 7;

    /**
     * UI / legacy badge labels used by the Vue discover cards.
     */
    public static function badge(int $status): string
    {
        return match ($status) {
            self::AVAILABLE => 'AVAILABLE',
            self::PROCESSING => 'DOWNLOADING',
            self::PENDING, self::PARTIALLY_AVAILABLE => 'MONITORED',
            self::BLOCKLISTED => 'BLOCKLISTED',
            default => 'UNREQUESTED',
        };
    }

    public static function fromArrMovie(bool $hasFile, bool $monitored): int
    {
        if ($hasFile) {
            return self::AVAILABLE;
        }

        return $monitored ? self::PENDING : self::UNKNOWN;
    }

    public static function fromArrSeries(float $percentOfEpisodes, bool $monitored): int
    {
        if ($percentOfEpisodes >= 100) {
            return self::AVAILABLE;
        }

        if ($percentOfEpisodes > 0) {
            return self::PARTIALLY_AVAILABLE;
        }

        return $monitored ? self::PENDING : self::UNKNOWN;
    }
}
