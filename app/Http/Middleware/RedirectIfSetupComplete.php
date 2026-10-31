<?php

namespace App\Http\Middleware;

use App\Models\ServiceConfig;
use App\Support\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! ServiceConfig::setupComplete()) {
            return $next($request);
        }

        // Admins may revisit setup/settings after first run.
        if ($request->user()?->hasPermission([Permission::ADMIN, Permission::MANAGE_SETTINGS], 'or')) {
            return $next($request);
        }

        return redirect()->route('discover');
    }
}