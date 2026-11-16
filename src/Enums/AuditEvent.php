<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Enums;

enum AuditEvent: string
{
    case RoleCreated = 'role.created';
    case RoleUpdated = 'role.updated';
    case RoleDeleted = 'role.deleted';
    case RoleRestored = 'role.restored';
    case RoleForceDeleted = 'role.force_deleted';
    case RoleCloned = 'role.cloned';
    case PermissionsSynced = 'permissions.synced';
    case PermissionsGrantedToRole = 'permissions.granted';
    case PermissionsRevokedFromRole = 'permissions.revoked';
    case RoleAssignedToUser = 'role.assigned';
    case RoleRemovedFromUser = 'role.removed';
    case ModulesSynced = 'modules.synced';
    case RolesImported = 'roles.imported';
    case SuperAdminProtected = 'super_admin.protected';
    case DirectPermissionChanged = 'permission.direct';
}
