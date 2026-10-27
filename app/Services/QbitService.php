<?php

namespace App\Services;

use App\Models\ServiceConfig;
use App\Services\Concerns\ResolvesServiceConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class QbitService
{
    use ResolvesServiceConfig;

    private const SESSION_CACHE_KEY = 'qbit_session_cookie';

    public function __construct(private ?ServiceConfig $config = null) {}

    /**
     * @param  array{base_url: string, username?: string|null, password?: string|null, api_key?: string|null}  $credentials
     */
    public static function testConnection(
        string $baseUrl,
        ?string $username = null,
        ?string $password = null,
        ?string $apiKey = null
    ): array {
        $base = rtrim($baseUrl, '/');
        $client = static::authenticatedClient($base, $username, $password, $apiKey);

        $version = $client->get('/api/v2/app/version');

        if ($version->failed()) {
            throw new RuntimeException(
                'Authenticated request failed (HTTP '.$version->status().': '.trim($version->body() ?: 'empty').').'
            );
        }

        $mode = filled($apiKey) ? 'API key' : 'username/password';

        return [
            'ok' => true,
            'message' => "qBittorrent connection successful via {$mode} (".$version->body().').',
        ];
    }

    private function config(): ServiceConfig
    {
        return $this->resolveConfig('qbittorrent', $this->config);
    }

    private function request(): PendingRequest
    {
        $config = $this->config();

        return static::authenticatedClient(
            $this->normalizedBaseUrl($config),
            $config->username,
            $config->password,
            $config->api_key
        )->timeout(5);
    }

    private static function authenticatedClient(
        string $baseUrl,
        ?string $username,
        ?string $password,
        ?string $apiKey
    ): PendingRequest {
        $base = rtrim($baseUrl, '/');
        $headers = static::browserHeaders($base);

        if (filled($apiKey)) {
            return Http::baseUrl($base)
                ->withHeaders([
                    ...$headers,
                    'Authorization' => 'Bearer '.$apiKey,
                ])
                ->timeout(15);
        }

        if (! filled($username) || ! filled($password)) {
            throw new RuntimeException('Provide either a qBittorrent API key, or username + password.');
        }

        $session = static::loginSession($base, $username, $password);
        $parsed = parse_url($base);
        $domain = $parsed['host'] ?? 'localhost';

        return Http::baseUrl($base)
            ->withHeaders($headers)
            ->withCookies([$session['name'] => $session['value']], $domain)
            ->timeout(15);
    }

    /**
     * @return array{name: string, value: string}
     */
    private static function loginSession(string $baseUrl, string $username, string $password): array
    {
        $cacheKey = self::SESSION_CACHE_KEY.':'.md5($baseUrl.'|'.$username);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ! empty($cached['name']) && ! empty($cached['value'])) {
            return $cached;
        }

        try {
            $response = Http::asForm()
                ->baseUrl($baseUrl)
                ->withHeaders(static::browserHeaders($baseUrl))
                ->timeout(15)
                ->post('/api/v2/auth/login', [
                    'username' => $username,
                    'password' => $password,
                ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Could not reach qBittorrent: '.$e->getMessage());
        }

        $body = trim($response->body());
        $cookie = static::extractSessionCookie($response);

        // qBit ≤5.1: 200 + "Ok." + SID
        // qBit ≥5.2: 204 + empty body + QBT_SID_<port>
        $looksSuccessful = $response->successful()
            && ($body === '' || $body === 'Ok.' || $body === 'Ok' || $cookie !== null);

        if ($looksSuccessful && $cookie) {
            Cache::put($cacheKey, $cookie, 1800);

            return $cookie;
        }

        if ($response->successful() && ! $cookie) {
            throw new RuntimeException(
                'Login succeeded (HTTP '.$response->status().'), but no session cookie was returned. '
                .'A reverse proxy may be stripping Set-Cookie. Prefer the WebUI API key (qBittorrent 5.2+).'
            );
        }

        $hint = match (true) {
            $response->status() === 401 || str_contains(strtolower($body), 'unauthorized') || str_contains(strtolower($body), 'fails') =>
                'Wrong WebUI username/password.',
            $response->status() === 403 || str_contains(strtolower($body), 'forbidden') || str_contains(strtolower($body), 'banned') =>
                'Forbidden/banned IP — restart qBittorrent or set Ban failures to 0 temporarily.',
            default => 'Unexpected login response.',
        };

        throw new RuntimeException(
            "qBittorrent login failed (HTTP {$response->status()}: ".($body !== '' ? $body : 'empty body').'). '.$hint
        );
    }

    /**
     * @return array{name: string, value: string}|null
     */
    private static function extractSessionCookie(Response $response): ?array
    {
        foreach ($response->cookies() as $cookie) {
            $name = $cookie->getName();
            if (static::isSessionCookieName($name) && $cookie->getValue() !== '') {
                return ['name' => $name, 'value' => $cookie->getValue()];
            }
        }

        foreach ($response->headers() as $headerName => $values) {
            if (strtolower((string) $headerName) !== 'set-cookie') {
                continue;
            }

            foreach ($values as $header) {
                if (preg_match('/(?:^|,\s*)((?:SID|QBT_SID_\d+))=([^;,\s]+)/i', $header, $matches)) {
                    return [
                        'name' => $matches[1],
                        'value' => urldecode($matches[2]),
                    ];
                }
            }
        }

        return null;
    }

    private static function isSessionCookieName(string $name): bool
    {
        return strcasecmp($name, 'SID') === 0
            || (bool) preg_match('/^QBT_SID_\d+$/i', $name);
    }

    private static function browserHeaders(string $baseUrl): array
    {
        return [
            'Referer' => $baseUrl.'/',
            'Origin' => $baseUrl,
            'Accept' => '*/*',
            'User-Agent' => 'Omniseerr/1.0 (qBittorrent WebAPI client)',
        ];
    }

    public function getTorrents(): array
    {
        try {
            $res = $this->request()->get('/api/v2/torrents/info');

            if ($res->status() === 403 || $res->status() === 401) {
                Cache::forget(self::SESSION_CACHE_KEY);
                // Also clear hashed cache keys by forcing re-login on next request:
                $config = $this->config();
                Cache::forget(self::SESSION_CACHE_KEY.':'.md5($this->normalizedBaseUrl($config).'|'.$config->username));
                $res = $this->request()->get('/api/v2/torrents/info');
            }

            return $res->json() ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  string  $action  pause|resume|stop|start|recheck|delete|deletePerm
     */
    public function controlTorrent(string $hash, string $action): void
    {
        $hash = strtolower($hash);

        if ($action === 'delete' || $action === 'deletePerm') {
            $this->request()->asForm()->post('/api/v2/torrents/delete', [
                'hashes' => $hash,
                'deleteFiles' => $action === 'deletePerm' ? 'true' : 'false',
            ]);

            return;
        }

        $endpoint = match ($action) {
            'pause' => 'pause',
            'resume' => 'resume',
            'stop' => 'stop',
            'start' => 'start',
            'recheck' => 'recheck',
            default => throw new RuntimeException("Unsupported torrent action: {$action}"),
        };

        $response = $this->request()->asForm()->post("/api/v2/torrents/{$endpoint}", [
            'hashes' => $hash,
        ]);

        if ($response->failed() && in_array($action, ['pause', 'resume', 'stop', 'start'], true)) {
            $fallback = match ($action) {
                'pause' => 'stop',
                'resume' => 'start',
                'stop' => 'pause',
                'start' => 'resume',
            };

            $this->request()->asForm()->post("/api/v2/torrents/{$fallback}", [
                'hashes' => $hash,
            ]);
        }
    }
}
