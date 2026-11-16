<?php

declare(strict_types=1);

use ImranDevBD\RoleMatrix\Facades\RoleMatrix;

/**
 * Sketch of an ERP registry. The workbench catalog is the runnable version.
 */
RoleMatrix::modules(function (): void {
    RoleMatrix::module('inventory')->group('Operations')->crud()->dependsOn('products');
    RoleMatrix::module('sales-orders')->group('Sales')->workflow();
    RoleMatrix::module('invoices')->group('Accounting')->workflow()
        ->conditionable(['ownership', 'status', 'amount']);
    RoleMatrix::module('payroll')->group('Payroll')->workflow();
    RoleMatrix::module('finance-report')->group('Reports')->reportable();
});
