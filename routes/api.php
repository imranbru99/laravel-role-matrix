<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use ImranDevBD\RoleMatrix\Http\Controllers\Api\RoleMatrixApiController;

Route::middleware(config('role-matrix.api.middleware', ['api']))
    ->prefix(trim((string) config('role-matrix.api.prefix', 'api/role-matrix'), '/'))
    ->group(function (): void {
        Route::get('roles', [RoleMatrixApiController::class, 'roles']);
        Route::get('permissions', [RoleMatrixApiController::class, 'permissions']);
        Route::post('explain', [RoleMatrixApiController::class, 'explain']);
    });
