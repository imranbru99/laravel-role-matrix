<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use ImranDevBD\RoleMatrix\Contracts\PermissionResolverContract;
use ImranDevBD\RoleMatrix\Http\Resources\RoleResource;
use ImranDevBD\RoleMatrix\Models\Permission;
use ImranDevBD\RoleMatrix\Models\Role;
use ImranDevBD\RoleMatrix\Support\RoleMatrixConfig;

final class RoleMatrixApiController extends Controller
{
    public function roles(Request $request): JsonResponse
    {
        $this->guard($request);
        $roles = Role::query()->with('permissions')->orderByDesc('level')->get();

        return RoleResource::collection($roles)->response();
    }

    public function permissions(Request $request): JsonResponse
    {
        $this->guard($request);

        return response()->json([
            'data' => Permission::query()->orderBy('name')->get(['name', 'guard_name', 'module_slug', 'action', 'group']),
        ]);
    }

    public function explain(Request $request, PermissionResolverContract $resolver): JsonResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'user_id' => ['required'],
            'permission' => ['required', 'string'],
        ]);
        $user = RoleMatrixConfig::model('user')::query()->findOrFail($data['user_id']);

        return response()->json($resolver->explain($user, $data['permission'])->toArray());
    }

    private function guard(Request $request): void
    {
        $user = $request->user();
        $super = $user !== null && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
        abort_unless($user !== null && method_exists($user, 'hasPermissionTo') && ($super || $user->hasPermissionTo('role-matrix.view')), 403);
    }
}
