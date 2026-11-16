<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleOrPermissionMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$items): Response
    {
        $user = $request->user();
        $allowed = $user !== null && (
            (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(...$items))
            || (method_exists($user, 'hasAnyPermission') && $this->permissions($user, $items))
        );

        abort_unless($allowed, 403);

        return $next($request);
    }

    /**
     * @param  list<string>  $items
     */
    private function permissions(object $user, array $items): bool
    {
        foreach ($items as $item) {
            if (str_contains($item, '.') && $user->hasPermissionTo($item)) {
                return true;
            }
        }

        return false;
    }
}
