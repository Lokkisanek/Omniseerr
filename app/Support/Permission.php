<?php

namespace App\Support;

/**
 * Overseerr / Seerr-compatible permission bitmask.
 *
 * @see https://github.com/seerr-team/seerr/blob/develop/server/lib/permissions.ts
 */
final class Permission
{
    public const NONE = 0;

    public const ADMIN = 2;

    public const MANAGE_SETTINGS = 4;

    public const MANAGE_USERS = 8;

    public const MANAGE_REQUESTS = 16;

    public const REQUEST = 32;

    public const AUTO_APPROVE = 128;

    public const AUTO_APPROVE_MOVIE = 256;

    public const AUTO_APPROVE_TV = 512;

    public const REQUEST_MOVIE = 262144;

    public const REQUEST_TV = 524288;

    public const VIEW_ACTIVITY = 67108864;

    /** Default permissions for newly invited local users. */
    public const DEFAULT_USER = self::REQUEST
        | self::REQUEST_MOVIE
        | self::REQUEST_TV
        | self::VIEW_ACTIVITY;

    public static function labels(): array
    {
        return [
            self::ADMIN => 'Admin',
            self::MANAGE_SETTINGS => 'Manage Settings',
            self::MANAGE_USERS => 'Manage Users',
            self::MANAGE_REQUESTS => 'Manage Requests / Downloads',
            self::REQUEST => 'Request / Grab',
            self::REQUEST_MOVIE => 'Request Movies',
            self::REQUEST_TV => 'Request Series',
            self::VIEW_ACTIVITY => 'View Activity',
            self::AUTO_APPROVE => 'Auto-Approve',
            self::AUTO_APPROVE_MOVIE => 'Auto-Approve Movies',
            self::AUTO_APPROVE_TV => 'Auto-Approve Series',
        ];
    }

    /**
     * @param  int|array<int>  $permissions
     */
    public static function check(int|array $permissions, int $value, string $type = 'and'): bool
    {
        if ($permissions === 0 || $permissions === []) {
            return true;
        }

        if ($value & self::ADMIN) {
            return true;
        }

        if (is_array($permissions)) {
            return $type === 'or'
                ? collect($permissions)->contains(fn (int $p) => (bool) ($value & $p))
                : collect($permissions)->every(fn (int $p) => (bool) ($value & $p));
        }

        return (bool) ($value & $permissions);
    }
}
