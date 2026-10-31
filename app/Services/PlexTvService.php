<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PlexTvService
{
    public function __construct(private string $authToken) {}

    /**
     * @return array{id:int,uuid:?string,email:string,username:string,title:?string,thumb:?string,authToken:string}
     */
    public function getUser(): array
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'X-Plex-Token' => $this->authToken,
            'X-Plex-Product' => 'Omniseerr',
            'X-Plex-Version' => '1.0',
            'X-Plex-Client-Identifier' => config('omniseerr.plex_client_id', 'omniseerr'),
        ])
            ->timeout(15)
            ->get('https://plex.tv/users/account.json');

        if ($response->failed()) {
            throw new RuntimeException('Invalid Plex auth token.');
        }

        $user = $response->json('user');

        if (! is_array($user) || empty($user['id']) || empty($user['email'])) {
            throw new RuntimeException('Unexpected Plex account response.');
        }

        return [
            'id' => (int) $user['id'],
            'uuid' => $user['uuid'] ?? null,
            'email' => (string) $user['email'],
            'username' => (string) ($user['username'] ?? $user['title'] ?? $user['email']),
            'title' => $user['title'] ?? null,
            'thumb' => $user['thumb'] ?? null,
            'authToken' => (string) ($user['authToken'] ?? $this->authToken),
        ];
    }
}
