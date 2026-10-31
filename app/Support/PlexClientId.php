<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class PlexClientId
{
    public static function get(): string
    {
        if ($fromEnv = config('omniseerr.plex_client_id')) {
            return (string) $fromEnv;
        }

        $path = self::path();

        if (File::exists($path)) {
            $existing = trim((string) File::get($path));
            if ($existing !== '') {
                return $existing;
            }
        }

        $id = (string) Str::uuid();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $id);

        return $id;
    }

    private static function path(): string
    {
        if (is_dir('/config') && is_writable('/config')) {
            return '/config/plex-client-id';
        }

        return storage_path('app/plex-client-id');
    }
}
