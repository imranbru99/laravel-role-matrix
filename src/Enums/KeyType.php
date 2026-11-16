<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Enums;

enum KeyType: string
{
    case Int = 'int';
    case Ulid = 'ulid';
    case Uuid = 'uuid';

    public static function fromConfig(?string $value = null): self
    {
        return match ($value ?? (string) config('role-matrix.key_type', 'int')) {
            'ulid' => self::Ulid,
            'uuid' => self::Uuid,
            default => self::Int,
        };
    }
}
