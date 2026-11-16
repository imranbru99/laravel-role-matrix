<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Support;

final class Checksum
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function sign(array $payload): array
    {
        unset($payload['checksum']);
        $payload['checksum'] = hash('sha256', self::canonical($payload));

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function valid(array $payload): bool
    {
        if (! isset($payload['checksum']) || ! is_string($payload['checksum'])) {
            return false;
        }

        $checksum = $payload['checksum'];
        unset($payload['checksum']);

        return hash_equals($checksum, hash('sha256', self::canonical($payload)));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function canonical(array $payload): string
    {
        return json_encode(self::sort($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function sort(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::sort($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
