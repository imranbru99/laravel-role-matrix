<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ImranDevBD\RoleMatrix\Contracts\ImportExportServiceContract;
use ImranDevBD\RoleMatrix\Exceptions\ImportValidationException;

final class ImportController extends Controller
{
    public function store(Request $request, ImportExportServiceContract $imports): RedirectResponse
    {
        $user = $request->user();
        $super = method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
        abort_unless($user !== null && method_exists($user, 'hasPermissionTo') && ($super || $user->hasPermissionTo('role-matrix.import')), 403);

        $decoded = json_decode((string) $request->input('payload'), true);

        if (! is_array($decoded)) {
            return back()->withErrors(['payload' => 'Invalid JSON.']);
        }

        try {
            $report = $imports->import($decoded, (string) $request->input('mode', 'dry-run'), $request->boolean('create_missing'));
        } catch (ImportValidationException $exception) {
            return back()->withErrors(['payload' => $exception->getMessage()]);
        }

        return back()->with('role_matrix_import', $report);
    }
}
