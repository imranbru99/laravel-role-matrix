<?php

declare(strict_types=1);

namespace ImranDevBD\RoleMatrix\Support;

use ImranDevBD\RoleMatrix\Exceptions\CircularHierarchyException;

final class HierarchyWalker
{
    /**
     * @param  array<string, string|null>  $parents
     * @return list<string>
     */
    public static function ancestors(string $id, array $parents, int $maxDepth, bool $throwOnCycle = false): array
    {
        $ancestors = [];
        $seen = [$id => true];
        $current = $parents[$id] ?? null;
        $depth = 0;

        while ($current !== null && $current !== '' && $depth < $maxDepth) {
            if (isset($seen[$current])) {
                if ($throwOnCycle) {
                    throw new CircularHierarchyException("Role [{$id}] is part of a parent cycle.");
                }

                break;
            }

            $seen[$current] = true;
            $ancestors[] = $current;
            $current = $parents[$current] ?? null;
            $depth++;
        }

        return $ancestors;
    }

    /**
     * @param  array<string, string|null>  $parents
     * @return list<string>
     */
    /**
     * @param  array<string, string|null>  $parents
     * @param  array<string, true>  $seen
     * @return list<string>
     */
    public static function descendants(string $id, array $parents, array $seen = []): array
    {
        if (isset($seen[$id])) {
            return [];
        }

        $seen[$id] = true;
        $all = [];

        foreach ($parents as $roleId => $parentId) {
            if ($parentId !== $id || isset($seen[$roleId])) {
                continue;
            }

            $all[] = $roleId;
            $all = array_merge($all, self::descendants($roleId, $parents, $seen));
        }

        return array_values(array_unique($all));
    }

    /**
     * @param  array<string, string|null>  $parents
     * @return list<list<string>>
     */
    public static function cycles(array $parents): array
    {
        $cycles = [];
        $visited = [];

        foreach (array_keys($parents) as $start) {
            if (isset($visited[$start])) {
                continue;
            }

            $path = [];
            $cursor = $start;
            $local = [];

            while ($cursor !== null && $cursor !== '' && ! isset($visited[$cursor])) {
                if (isset($local[$cursor])) {
                    $cycle = array_keys($local);
                    $from = array_search($cursor, $cycle, true);
                    $cycles[] = array_slice($cycle, is_int($from) ? $from : 0);
                    break;
                }

                $local[$cursor] = true;
                $path[] = $cursor;
                $cursor = $parents[$cursor] ?? null;
            }

            foreach (array_keys($local) as $seen) {
                $visited[$seen] = true;
            }
        }

        return $cycles;
    }
}
