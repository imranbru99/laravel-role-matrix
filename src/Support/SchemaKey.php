<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

final class SchemaKey
{
    public static function id(Blueprint $table): void
    {
        match (RoleMatrixConfig::keyType()) {
            \ImranDevBD\RoleMatrix\Enums\KeyType::Ulid => $table->ulid('id')->primary(),
            \ImranDevBD\RoleMatrix\Enums\KeyType::Uuid => $table->uuid('id')->primary(),
            default => $table->id(),
        };
    }

    public static function foreignColumn(Blueprint $table, string $column, bool $nullable = false): void
    {
        $columnDefinition = match (RoleMatrixConfig::keyType()) {
            \ImranDevBD\RoleMatrix\Enums\KeyType::Ulid => $table->ulid($column),
            \ImranDevBD\RoleMatrix\Enums\KeyType::Uuid => $table->uuid($column),
            default => $table->unsignedBigInteger($column),
        };

        if ($nullable) {
            $columnDefinition->nullable();
        }
    }

    public static function newKey(): ?string
    {
        return match (RoleMatrixConfig::keyType()) {
            \ImranDevBD\RoleMatrix\Enums\KeyType::Ulid => (string) Str::ulid(),
            \ImranDevBD\RoleMatrix\Enums\KeyType::Uuid => (string) Str::uuid(),
            default => null,
        };
    }
}
