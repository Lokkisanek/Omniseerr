<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GhostMedia extends Model
{
    protected $table = 'ghost_media';

    protected $fillable = [
        'media_type',
        'tmdb_id',
        'external_arr_id',
    ];

    protected function casts(): array
    {
        return [
            'tmdb_id' => 'integer',
            'external_arr_id' => 'integer',
        ];
    }
}
