<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        abort_unless($user !== null && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(...$roles), 403);

        return $next($request);
    }
}
