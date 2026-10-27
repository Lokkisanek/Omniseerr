<?php

namespace App\Console\Commands;

use App\Models\GhostMedia;
use App\Models\ServiceConfig;
use App\Services\RadarrService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CleanupGhostMedia extends Command
{
    protected $signature = 'omniseerr:cleanup-ghosts {--minutes=15 : Age threshold in minutes}';

    protected $description = 'Remove abandoned Ghost-Add movies/series from Radarr/Sonarr';

    public function handle(RadarrService $radarr): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $cutoff = now()->subMinutes($minutes);

        $ghosts = GhostMedia::query()->where('created_at', '<=', $cutoff)->get();

        if ($ghosts->isEmpty()) {
            $this->info('No abandoned ghost media found.');

            return self::SUCCESS;
        }

        foreach ($ghosts as $ghost) {
            try {
                if ($ghost->media_type === 'movie' && ServiceConfig::for('radarr')?->is_enabled) {
                    $radarr->deleteGhostMovie($ghost->external_arr_id);
                    $this->line("Cleaned ghost movie #{$ghost->external_arr_id}");
                }
                // Series cleanup will mirror movies once interactive TV grab is complete.
            } catch (Throwable $e) {
                Log::warning('Ghost cleanup failed', [
                    'ghost_id' => $ghost->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed cleaning ghost #{$ghost->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
