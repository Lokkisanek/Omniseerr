<?php

use App\Support\Permission;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('downloads', function ($user) {
    if (! $user || ! $user->is_active) {
        return false;
    }

    return $user->hasPermission([
        Permission::ADMIN,
        Permission::RECENT_VIEW,
        Permission::MANAGE_REQUESTS,
    ], 'or');
});
