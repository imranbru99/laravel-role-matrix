<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Support;

use Illuminate\Database\Eloquent\Model;

final class ActorAttributes
{
    /**
     * @return list<string>
     */
    public static function ids(Model $user, string $attribute, string $method, bool $withChildren = false): array
    {
        $childMethod = $method.'IncludingChildren';

        if ($withChildren && method_exists($user, $childMethod)) {
            $ids = $user->{$childMethod}();

            return self::stringify(is_array($ids) ? $ids : []);
        }

        if (method_exists($user, $method)) {
            $ids = $user->{$method}();

            return self::stringify(is_array($ids) ? $ids : []);
        }

        $value = $user->getAttribute($attribute);

        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return self::stringify($value);
        }

        return [(string) $value];
    }

    /**
     * @param  array<mixed>  $ids
     * @return list<string>
     */
    private static function stringify(array $ids): array
    {
        return array_values(array_map(static fn (mixed $id): string => (string) $id, $ids));
    }
}
