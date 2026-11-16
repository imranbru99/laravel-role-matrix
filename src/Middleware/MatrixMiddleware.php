<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MatrixMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $permission, ?string $parameter = null): Response
    {
        $user = $request->user();
        $record = $parameter !== null ? $request->route($parameter) : null;
        abort_unless(
            $user !== null && method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($permission, $record),
            403,
        );

        return $next($request);
    }
}
