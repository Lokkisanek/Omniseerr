<?php

namespace App\Http\Middleware;

use App\Models\ServiceConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if (ServiceConfig::setupComplete() && ! $request->boolean('force')) {
            return redirect()->route('discover');
        }

        return $next($request);
    }
}
