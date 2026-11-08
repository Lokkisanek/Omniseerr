<?php

namespace App\Console\Commands;

use App\Models\GhostMedia;
use App\Models\ServiceConfig;
use App\Services\RadarrService;
use App\Services\SonarrService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CleanupGhostMedia extends Command
{
    protected $signature = 'omniseerr:cleanup-ghosts {--minutes=15 : Age threshold in minutes}';

    protected $description = 'Remove abandoned Ghost-Add movies/series from Radarr/Sonarr';

    public function handle(RadarrService $radarr, SonarrService $sonarr): int
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
                if (in_array($ghost->media_type, ['movie'], true) && ServiceConfig::for('radarr')?->is_enabled) {
                    $radarr->deleteGhostMovie($ghost->external_arr_id);
                    $this->line("Cleaned ghost movie #{$ghost->external_arr_id}");
                }

                if (in_array($ghost->media_type, ['tv', 'series'], true) && ServiceConfig::for('sonarr')?->is_enabled) {
                    $sonarr->deleteGhostSeries($ghost->external_arr_id);
                    $this->line("Cleaned ghost series #{$ghost->external_arr_id}");
                }
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
