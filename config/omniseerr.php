<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Plex OAuth client identifier
    |--------------------------------------------------------------------------
    |
    | Stable ID sent as X-Plex-Client-Identifier. Persist across restarts via
    | /config (Docker) or storage. Generated on first boot if missing.
    |
    */
    'plex_client_id' => env('PLEX_CLIENT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Allow automatic account creation for new Plex logins
    |--------------------------------------------------------------------------
    */
    'plex_allow_new_users' => env('PLEX_ALLOW_NEW_USERS', true),

    /*
    |--------------------------------------------------------------------------
    | Bundled TMDB application key (Overseerr/Seerr style — not asked in setup)
    |--------------------------------------------------------------------------
    |
    | Override with TMDB_API_KEY if you want your own application key.
    |
    */
    'tmdb_api_key' => env('TMDB_API_KEY', 'db55323b8d3e4154498498a75642b381'),
    'tmdb_base_url' => env('TMDB_BASE_URL', 'https://api.themoviedb.org/3'),
];
