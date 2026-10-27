<?php

namespace App\Console\Commands;

use App\Events\DownloadQueueUpdated;
use App\Models\ServiceConfig;
use App\Services\TelemetryAggregator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PollDownloadTelemetry extends Command
{
    protected $signature = 'omniseerr:poll-telemetry {--interval=2 : Seconds between polls}';

    protected $description = 'Poll Radarr/Sonarr/qBittorrent and broadcast live download telemetry while clients are connected';

    public function handle(TelemetryAggregator $aggregator): int
    {
        $interval = max(1, (int) $this->option('interval'));
        $this->info("Omniseerr telemetry poller started (every {$interval}s, only while clients connected).");

        while (true) {
            try {
                if ($this->hasConnectedClients() && ServiceConfig::setupComplete()) {
                    $queue = $aggregator->getUnifiedQueue();
                    Cache::put('omniseerr.live_queue', $queue, 30);
                    broadcast(new DownloadQueueUpdated($queue));
                }
            } catch (Throwable $e) {
                $this->error('Telemetry poll failed: '.$e->getMessage());
            }

            sleep($interval);
        }
    }

    private function hasConnectedClients(): bool
    {
        return (int) Cache::get('omniseerr.ws_clients', 0) > 0;
    }
}
