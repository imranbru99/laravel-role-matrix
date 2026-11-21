# Changelog

## 1.1.0 - 2026-11-21

Stable release. Composer installs `1.1.0` instead of `dev-main`.

- Permission enum generator: `role-matrix:make-permission-enum`.
- Filament helpers: `RolesColumn`, `RolesFilter`, and `AssignRolesAction`.
- Matrix focus view renders one role as cards.
- Condition drawer edits each rule field, not only the raw JSON.
- Package stability is `stable`.

## 1.0.0 - 2026-11-16

First stable release.

- Native roles, permissions, hierarchy, direct allow/deny, temporary roles, teams, and audit log.
- Module registry with presets, dependencies, and discovery.
- Conditional grants and SQL scopes for ownership, team, department, status, and literal attributes.
- Filament 3 and 4 plugin with a single batched matrix save, plus a standalone Blade UI.
- Import/export schema `role-matrix/1`, clone, and the optional Spatie name mirror.
- English and Bangla translations.
