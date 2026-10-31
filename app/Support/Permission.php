<?php

namespace App\Support;

/**
 * Seerr-compatible permission bitmask.
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

    public const VOTE = 64;

    public const AUTO_APPROVE = 128;

    public const AUTO_APPROVE_MOVIE = 256;

    public const AUTO_APPROVE_TV = 512;

    public const REQUEST_4K = 1024;

    public const REQUEST_4K_MOVIE = 2048;

    public const REQUEST_4K_TV = 4096;

    public const REQUEST_ADVANCED = 8192;

    public const REQUEST_VIEW = 16384;

    public const AUTO_APPROVE_4K = 32768;

    public const AUTO_APPROVE_4K_MOVIE = 65536;

    public const AUTO_APPROVE_4K_TV = 131072;

    public const REQUEST_MOVIE = 262144;

    public const REQUEST_TV = 524288;

    public const MANAGE_ISSUES = 1048576;

    public const VIEW_ISSUES = 2097152;

    public const CREATE_ISSUES = 4194304;

    public const AUTO_REQUEST = 8388608;

    public const AUTO_REQUEST_MOVIE = 16777216;

    public const AUTO_REQUEST_TV = 33554432;

    /** Seerr: RECENT_VIEW — Omniseerr uses this for Activity / live downloads. */
    public const RECENT_VIEW = 67108864;

    /** @deprecated Use RECENT_VIEW (same bit). */
    public const VIEW_ACTIVITY = self::RECENT_VIEW;

    public const WATCHLIST_VIEW = 134217728;

    public const MANAGE_BLOCKLIST = 268435456;

    public const VIEW_BLOCKLIST = 1073741824;

    /** Default permissions for newly created non-admin users (Seerr-like). */
    public const DEFAULT_USER = self::REQUEST
        | self::REQUEST_MOVIE
        | self::REQUEST_TV
        | self::RECENT_VIEW;

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
            self::REQUEST_ADVANCED => 'Advanced Request',
            self::RECENT_VIEW => 'View Activity',
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
