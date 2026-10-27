<?php

namespace App\Services;

use App\Models\ServiceConfig;
use App\Services\Concerns\ResolvesServiceConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class QbitService
{
    use ResolvesServiceConfig;

    private const SESSION_CACHE_KEY = 'qbit_sid_cookie';

    public function __construct(private ?ServiceConfig $config = null) {}

    public static function testConnection(string $baseUrl, string $username, string $password): array
    {
        $response = Http::asForm()
            ->baseUrl(rtrim($baseUrl, '/'))
            ->timeout(10)
            ->post('/api/v2/auth/login', [
                'username' => $username,
                'password' => $password,
            ]);

        if ($response->body() !== 'Ok.' && $response->status() !== 200) {
            throw new RuntimeException('qBittorrent login failed.');
        }

        $cookie = $response->cookies()->getCookieByName('SID');

        if (! $cookie || ! $cookie->getValue()) {
            throw new RuntimeException('qBittorrent did not return a session cookie.');
        }

        return [
            'ok' => true,
            'message' => 'qBittorrent connection successful.',
        ];
    }

    private function config(): ServiceConfig
    {
        return $this->resolveConfig('qbittorrent', $this->config);
    }

    private function getSessionCookie(): string
    {
        $cached = Cache::get(self::SESSION_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $config = $this->config();

        $response = Http::asForm()
            ->baseUrl($this->normalizedBaseUrl($config))
            ->timeout(10)
            ->post('/api/v2/auth/login', [
                'username' => $config->username,
                'password' => $config->password,
            ]);

        $cookie = $response->cookies()->getCookieByName('SID');
        $sid = $cookie?->getValue() ?? '';

        if ($sid === '') {
            throw new RuntimeException('Unable to authenticate with qBittorrent.');
        }

        Cache::put(self::SESSION_CACHE_KEY, $sid, 1800);

        return $sid;
    }

    private function request(): PendingRequest
    {
        $config = $this->config();
        $parsedUrl = parse_url($config->base_url);
        $domain = $parsedUrl['host'] ?? 'localhost';
        $sid = $this->getSessionCookie();

        return Http::baseUrl($this->normalizedBaseUrl($config))
            ->withCookies(['SID' => $sid], $domain)
            ->timeout(5);
    }

    public function getTorrents(): array
    {
        try {
            $res = $this->request()->get('/api/v2/torrents/info');

            if ($res->status() === 403) {
                Cache::forget(self::SESSION_CACHE_KEY);
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

        // qBittorrent 4.x uses pause/resume; 5.x uses stop/start.
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

        // Fallback mapping between v4 and v5 action names.
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
