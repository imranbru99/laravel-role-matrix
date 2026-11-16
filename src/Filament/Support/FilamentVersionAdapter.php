<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Filament\Support;

final class FilamentVersionAdapter
{
    public static function major(): int
    {
        if (class_exists(\Filament\Schemas\Schema::class)) {
            return 4;
        }

        if (class_exists(\Filament\Panel::class)) {
            return 3;
        }

        return 0;
    }

    public static function isV4(): bool
    {
        return self::major() >= 4;
    }

    /**
     * @return class-string
     */
    public static function matrixPage(): string
    {
        return self::isV4()
            ? \ImranDevBD\RoleMatrix\Filament\V4\RoleMatrixPage::class
            : \ImranDevBD\RoleMatrix\Filament\V3\RoleMatrixPage::class;
    }

    /**
     * @return class-string
     */
    public static function roleResource(): string
    {
        return self::isV4()
            ? \ImranDevBD\RoleMatrix\Filament\V4\RoleResource::class
            : \ImranDevBD\RoleMatrix\Filament\V3\RoleResource::class;
    }

    /**
     * @return class-string
     */
    public static function auditPage(): string
    {
        return self::isV4()
            ? \ImranDevBD\RoleMatrix\Filament\V4\AuditLogPage::class
            : \ImranDevBD\RoleMatrix\Filament\V3\AuditLogPage::class;
    }

    /**
     * @return class-string
     */
    public static function importPage(): string
    {
        return self::isV4()
            ? \ImranDevBD\RoleMatrix\Filament\V4\ImportExportPage::class
            : \ImranDevBD\RoleMatrix\Filament\V3\ImportExportPage::class;
    }

    /**
     * @return class-string
     */
    public static function debuggerPage(): string
    {
        return self::isV4()
            ? \ImranDevBD\RoleMatrix\Filament\V4\AccessDebuggerPage::class
            : \ImranDevBD\RoleMatrix\Filament\V3\AccessDebuggerPage::class;
    }
}
