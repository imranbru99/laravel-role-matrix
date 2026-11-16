<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Services\AssignmentService;
use ImranDevBD\RoleMatrix\Services\MatrixBuilder;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;

final class MatrixController extends Controller
{
    public function index(Request $request, MatrixBuilder $builder): View
    {
        $this->authorizeAccess($request);
        $guard = $this->guard($request);

        return view('role-matrix::standalone.matrix', [
            'payload' => $builder->payload($guard),
            'assetCss' => asset('vendor/role-matrix/role-matrix.css'),
            'assetJs' => asset('vendor/role-matrix/role-matrix.js'),
            'saveUrl' => url(trim((string) config('role-matrix.standalone_ui.prefix', 'role-matrix'), '/')),
        ]);
    }

    public function update(Request $request, AssignmentService $assignments): RedirectResponse
    {
        $this->authorizeAccess($request);
        $changes = json_decode((string) $request->input('changes', '[]'), true);

        if (! is_array($changes)) {
            abort(422, 'Changes must be a JSON list.');
        }

        foreach ($changes as $change) {
            if (! is_array($change)) {
                continue;
            }

            $role = Role::query()->find($change['role_id'] ?? null);

            if (! $role instanceof Role) {
                continue;
            }

            $assignments->applyRoleDiff(
                $role,
                array_values($change['grant'] ?? []),
                array_values($change['revoke'] ?? []),
                is_array($change['conditions'] ?? null) ? $change['conditions'] : [],
            );
        }

        return back()->with('status', __('role-matrix::ui.saved'));
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return;
        }

        abort_unless(method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo('role-matrix.view'), 403);
    }

    private function guard(Request $request): string
    {
        $guard = $request->string('guard')->toString();

        return in_array($guard, RoleMatrixConfig::guards(), true) ? $guard : RoleMatrixConfig::defaultGuard();
    }
}
