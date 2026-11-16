<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        abort_unless($user !== null && method_exists($user, 'hasAnyPermission') && $user->hasAnyPermission(...$permissions), 403);

        return $next($request);
    }
}
