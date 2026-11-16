<?php

declare(strict_types=1);

/**
 * Headless clients use the optional JSON API.
 *
 * ROLE_MATRIX_API=true
 * Authorization: Bearer {sanctum token}
 *
 * GET  /api/role-matrix/roles
 * GET  /api/role-matrix/permissions
 * POST /api/role-matrix/explain   { "user_id": 1, "permission": "invoices.approve" }
 *
 * The caller must be a super admin or hold role-matrix.view.
 */
