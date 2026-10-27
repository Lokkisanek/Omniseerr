<?php

namespace App\Models;

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

    public static function setupComplete(): bool
    {
        $required = ['tmdb', 'radarr', 'qbittorrent'];

        $enabled = static::query()
            ->whereIn('service', $required)
            ->where('is_enabled', true)
            ->pluck('service')
            ->all();

        return count(array_intersect($required, $enabled)) === count($required);
    }
}
