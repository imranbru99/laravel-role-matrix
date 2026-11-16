<?php

declare(strict_types=1);

namespace Workbench\App\RoleMatrix;

use ImranDevBD\RoleMatrix\Facades\RoleMatrix;

final class Catalog
{
    public static function register(): void
    {
        foreach (self::modules() as $index => [$slug, $label, $group, $preset]) {
            $module = RoleMatrix::module($slug)->label($label)->group($group)->sort(($index + 1) * 10);

            match ($preset) {
                'workflow' => $module->workflow(),
                'report' => $module->reportable(),
                'read' => $module->readOnly(),
                default => $module->crud(),
            };
        }

        RoleMatrix::module('invoices', true)
            ->action('approve', label: 'Approve', danger: true, conditions: ['status'])
            ->conditionable(['ownership', 'status', 'amount', 'team'])
            ->dependsOn('customers');
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function modules(): array
    {
        return [
            ['inventory', 'Inventory', 'Operations', 'crud'],
            ['warehouses', 'Warehouses', 'Operations', 'crud'],
            ['transfers', 'Stock transfers', 'Operations', 'workflow'],
            ['adjustments', 'Stock adjustments', 'Operations', 'workflow'],
            ['products', 'Products', 'Catalog', 'crud'],
            ['categories', 'Categories', 'Catalog', 'crud'],
            ['brands', 'Brands', 'Catalog', 'crud'],
            ['price-lists', 'Price lists', 'Catalog', 'crud'],
            ['sales-orders', 'Sales orders', 'Sales', 'workflow'],
            ['quotations', 'Quotations', 'Sales', 'workflow'],
            ['customers', 'Customers', 'Sales', 'crud'],
            ['shipments', 'Shipments', 'Sales', 'workflow'],
            ['returns', 'Sales returns', 'Sales', 'workflow'],
            ['purchase-orders', 'Purchase orders', 'Purchase', 'workflow'],
            ['vendors', 'Vendors', 'Purchase', 'crud'],
            ['goods-receipts', 'Goods receipts', 'Purchase', 'workflow'],
            ['bills', 'Vendor bills', 'Purchase', 'workflow'],
            ['invoices', 'Invoices', 'Accounting', 'workflow'],
            ['payments', 'Payments', 'Accounting', 'workflow'],
            ['journals', 'Journals', 'Accounting', 'crud'],
            ['chart-of-accounts', 'Chart of accounts', 'Accounting', 'crud'],
            ['taxes', 'Taxes', 'Accounting', 'crud'],
            ['bank-accounts', 'Bank accounts', 'Accounting', 'crud'],
            ['reconciliation', 'Reconciliation', 'Accounting', 'workflow'],
            ['employees', 'Employees', 'HR', 'crud'],
            ['attendance', 'Attendance', 'HR', 'crud'],
            ['leave', 'Leave', 'HR', 'workflow'],
            ['recruitment', 'Recruitment', 'HR', 'workflow'],
            ['payroll', 'Payroll', 'Payroll', 'workflow'],
            ['payslips', 'Payslips', 'Payroll', 'report'],
            ['benefits', 'Benefits', 'Payroll', 'crud'],
            ['leads', 'Leads', 'CRM', 'workflow'],
            ['deals', 'Deals', 'CRM', 'workflow'],
            ['contacts', 'Contacts', 'CRM', 'crud'],
            ['activities', 'Activities', 'CRM', 'crud'],
            ['projects', 'Projects', 'Projects', 'crud'],
            ['tasks', 'Tasks', 'Projects', 'workflow'],
            ['timesheets', 'Timesheets', 'Projects', 'crud'],
            ['milestones', 'Milestones', 'Projects', 'crud'],
            ['sales-report', 'Sales report', 'Reports', 'report'],
            ['stock-report', 'Stock report', 'Reports', 'report'],
            ['finance-report', 'Finance report', 'Reports', 'report'],
            ['hr-report', 'HR report', 'Reports', 'report'],
            ['audit-report', 'Audit report', 'Reports', 'report'],
            ['company', 'Company', 'Settings', 'crud'],
            ['users', 'Users', 'Settings', 'crud'],
            ['teams', 'Teams', 'Settings', 'crud'],
            ['taxes-settings', 'Tax settings', 'Settings', 'crud'],
            ['notifications', 'Notifications', 'Settings', 'read'],
            ['integrations', 'Integrations', 'Settings', 'crud'],
            ['backups', 'Backups', 'Settings', 'workflow'],
        ];
    }
}
