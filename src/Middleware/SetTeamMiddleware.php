<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Middleware;

use Closure;
use Illuminate\Http\Request;
use ImranDevBD\RoleMatrix\Support\TeamContext;
use Symfony\Component\HttpFoundation\Response;

class SetTeamMiddleware
{
    public function __construct(private readonly TeamContext $teams) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $parameter = null): Response
    {
        $team = $parameter !== null ? $request->route($parameter) : $request->header('X-Team-Id');

        if (is_object($team) && method_exists($team, 'getKey')) {
            $team = $team->getKey();
        }

        $value = is_scalar($team) ? (string) $team : null;
        $this->teams->set($value);
        $request->attributes->set('role_matrix.team_id', $value);

        return $next($request);
    }
}
