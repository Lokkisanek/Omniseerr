<?php

namespace App\Http\Middleware;

use App\Support\Permission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403);
        }

        $required = array_map(
            fn (string $name) => constant(Permission::class.'::'.strtoupper($name)),
            $permissions
        );

        if (! $user->hasPermission($required, 'or')) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Insufficient permissions.'], 403);
            }

            abort(403, 'Insufficient permissions.');
        }

        return $next($request);
    }
}
