<?php

namespace App\Http\Middleware;

use App\Models\ServiceConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! ServiceConfig::setupComplete()) {
            return redirect()->route('setup');
        }

        return $next($request);
    }
}
