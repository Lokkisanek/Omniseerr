<?php

namespace App\Console\Commands;

use App\Services\MediaSyncService;
use Illuminate\Console\Command;

class SyncMediaLibrary extends Command
{
    protected $signature = 'omniseerr:sync-media';

    protected $description = 'Sync Radarr/Sonarr library state into local Media rows (Seerr-style availability cache)';

    public function handle(MediaSyncService $sync): int
    {
        $result = $sync->syncAll();
        $this->info("Synced {$result['movies']} movies and {$result['series']} series.");

        return self::SUCCESS;
    }
}
