<?php

namespace App\Models;

use App\Support\Permission;
use Illuminate\Database\Eloquent\Model;

class ServiceConfig extends Model
{
    protected $fillable = [
        'service',
        'base_url',
        'api_key',
        'username',
        'password',
        'defaults',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'password' => 'encrypted',
            'defaults' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    public static function for(string $service): ?self
    {
        return static::query()->where('service', $service)->first();
    }

    public static function servicesConfigured(): bool
    {
        // TMDB uses a bundled app key (like Overseerr/Seerr) — not part of setup.
        $required = ['radarr', 'qbittorrent'];

        $enabled = static::query()
            ->whereIn('service', $required)
            ->where('is_enabled', true)
            ->pluck('service')
            ->all();

        return count(array_intersect($required, $enabled)) === count($required);
    }

    public static function hasAdminUser(): bool
    {
        return User::query()
            ->where('is_active', true)
            ->get()
            ->contains(fn (User $user) => $user->hasPermission(Permission::ADMIN));
    }

    public static function setupComplete(): bool
    {
        return static::servicesConfigured() && static::hasAdminUser();
    }
}
