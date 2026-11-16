<?php

declare(strict_types=1);

use ImranDevBD\RoleMatrix\Facades\RoleMatrix;

/**
 * One database, many teams. Enable teams and set the team from the route.
 *
 * ROLE_MATRIX_TEAMS=true
 * middleware: role-matrix.team:team
 */
RoleMatrix::setTeam((string) request()->route('team'));

$user->assignRole('manager');
$user->hasPermissionTo('projects.view');
