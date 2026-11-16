<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use ImranDevBD\RoleMatrix\Http\Controllers\MatrixController;

$prefix = trim((string) config('role-matrix.standalone_ui.prefix', 'role-matrix'), '/');
$middleware = config('role-matrix.standalone_ui.middleware', ['web', 'auth']);

Route::middleware(is_array($middleware) ? $middleware : ['web', 'auth'])
    ->prefix($prefix)
    ->group(function (): void {
        Route::get('/', [MatrixController::class, 'index'])->name('role-matrix.ui');
        Route::post('/', [MatrixController::class, 'update'])->name('role-matrix.ui.update');
    });
