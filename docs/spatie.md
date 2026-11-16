# Spatie bridge

`ROLE_MATRIX_ENGINE=spatie` registers `SpatieCompatibilityServiceProvider`.

The native tables stay authoritative. After `PermissionsSynced`, `RoleAssignedToUser`, and `RoleRemovedFromUser`, the bridge copies role and permission names into Spatie's `roles` and `permissions` tables. Conditions, parent roles, expiry, and audit rows are not copied.

If Role Matrix is configured with an empty table prefix, both packages would write `roles`. The provider throws until you set `ROLE_MATRIX_TABLE_PREFIX=rm_`.

Use one trait on the user model. `HasRoleMatrix` reads the native tables. Spatie's `HasRoles` reads the mirror and will not see conditions.
