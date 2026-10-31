<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('omniseerr:cleanup-ghosts --minutes=15')->everyFiveMinutes();
Schedule::command('omniseerr:sync-media')->everyFiveMinutes();
