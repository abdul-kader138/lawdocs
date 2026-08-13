<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the Documents JSON API. Deliberately returns a plain 401 JSON body instead
 * of Laravel's default Authenticate middleware (which redirects to a 'login' route
 * that doesn't exist outside the Filament panel, and fetch() requests to this API
 * rarely set Accept: application/json so expectsJson() can't be relied on).
 */
class RequirePanelUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            abort(response()->json(['error' => 'Unauthenticated.'], 401));
        }

        return $next($request);
    }
}
