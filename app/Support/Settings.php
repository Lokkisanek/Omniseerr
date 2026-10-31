<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Seerr-style application settings (JSON document in SQLite).
 *
 * @see https://github.com/seerr-team/seerr/blob/develop/server/lib/settings.ts
 */
final class Settings
{
    public const MAIN_KEY = 'main';

    public static function defaults(): array
    {
        return [
            'applicationTitle' => 'Omniseerr',
            'applicationUrl' => '',
            'mediaServerType' => 'plex',
            'plexAllowNewUsers' => (bool) config('omniseerr.plex_allow_new_users', true),
            'localLogin' => true,
            'newPlexLogin' => true,
            'hideAvailable' => false,
            'discoverRegion' => 'CZ',
        ];
    }

    public static function main(): array
    {
        return Cache::remember('omniseerr.settings.main', 30, function () {
            $row = AppSetting::query()->where('key', self::MAIN_KEY)->first();

            return array_replace_recursive(self::defaults(), $row?->value ?? []);
        });
    }

    public static function setMain(array $patch): array
    {
        $merged = array_replace_recursive(self::main(), $patch);

        AppSetting::query()->updateOrCreate(
            ['key' => self::MAIN_KEY],
            ['value' => $merged]
        );

        Cache::forget('omniseerr.settings.main');

        return $merged;
    }

    public static function plexAllowNewUsers(): bool
    {
        $main = self::main();

        return (bool) ($main['plexAllowNewUsers'] ?? true)
            && (bool) ($main['newPlexLogin'] ?? true);
    }
}
