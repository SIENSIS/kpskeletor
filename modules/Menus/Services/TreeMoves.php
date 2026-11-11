<?php

declare(strict_types=1);

namespace Modules\Menus\Services;

/**
 * Moves in a tree of menu entries, shared by both editors (the buttons and keyboard shortcuts of
 * the tree editor, and the same moves without JavaScript).
 *
 * Nodes: key => ['parent' => ?string, 'zone' => string, 'order' => int]. Siblings share the
 * parent (and, at the root, the zone). Moves:
 * - `up` / `down`: swap with the previous / next sibling;
 * - `indent`: become the last child of the previous sibling;
 * - `outdent`: leave the parent and come right after it.
 *
 * Moved siblings are renumbered 10, 20, 30…; a node is never moved under itself or its
 * descendants (`isDescendant()`, `hasCycle()`).
 */
final class TreeMoves
{
    public const MOVES = ['up', 'down', 'indent', 'outdent'];

    /**
     * @param array<array-key, array{parent: ?string, zone: string, order: int}> $nodes
     *
     * @return array<array-key, array{parent: ?string, zone: string, order: int}>
     */
    public static function move(array $nodes, string $key, string $move): array
    {
        if (! isset($nodes[$key]) || ! in_array($move, self::MOVES, true)) {
            return $nodes;
        }

        $siblings = self::siblings($nodes, $nodes[$key]['parent'], $nodes[$key]['zone']);
        $index    = (int) array_search($key, $siblings, true);

        switch ($move) {
            case 'up':
            case 'down':
                $other = $siblings[$index + ($move === 'up' ? -1 : 1)] ?? null;

                if ($other === null) {
                    return $nodes;
                }

                $siblings[array_search($other, $siblings, true)] = $key;
                $siblings[$index]                                 = $other;

                return self::renumber($nodes, $siblings);

            case 'indent':
                $previous = $siblings[$index - 1] ?? null;

                if ($previous === null) {
                    return $nodes;
                }

                $children                = self::siblings($nodes, $previous, $nodes[$previous]['zone']);
                $nodes[$key]['parent']   = $previous;
                $nodes[$key]['zone']     = $nodes[$previous]['zone'];
                $nodes                   = self::renumber($nodes, [...$children, $key]);
                array_splice($siblings, $index, 1);

                return self::renumber($nodes, $siblings);

            default: // outdent
                $parent = $nodes[$key]['parent'];

                if ($parent === null || ! isset($nodes[$parent])) {
                    return $nodes;
                }

                array_splice($siblings, $index, 1);
                $nodes                 = self::renumber($nodes, $siblings);
                $nodes[$key]['parent'] = $nodes[$parent]['parent'];
                $nodes[$key]['zone']   = $nodes[$parent]['zone'];
                $outer                 = self::siblings($nodes, $nodes[$parent]['parent'], $nodes[$parent]['zone'], $key);
                $at                    = array_search($parent, $outer, true);
                array_splice($outer, is_int($at) ? $at + 1 : count($outer), 0, [$key]);

                return self::renumber($nodes, $outer);
        }
    }

    /**
     * The keys of the children of a parent (null = the root of a zone), in order.
     *
     * @param array<array-key, array{parent: ?string, zone: string, order: int}> $nodes
     *
     * @return list<string>
     */
    public static function siblings(array $nodes, ?string $parent, string $zone, ?string $except = null): array
    {
        $keys = [];

        foreach ($nodes as $key => $node) {
            if ($key !== $except && $node['parent'] === $parent && ($parent !== null || $node['zone'] === $zone)) {
                $keys[] = (string) $key;
            }
        }

        usort($keys, static fn (string $a, string $b): int => [$nodes[$a]['order'], $a] <=> [$nodes[$b]['order'], $b]);

        return $keys;
    }

    /**
     * True when `$candidate` is `$key` itself or one of its descendants.
     *
     * @param array<array-key, ?string> $parents key => parent key
     */
    public static function isDescendant(array $parents, string $key, string $candidate): bool
    {
        $seen = [];

        for ($node = $candidate; $node !== null; $node = $parents[$node] ?? null) {
            $node = (string) $node;

            if ($node === $key) {
                return true;
            }

            if (isset($seen[$node])) {
                return false;
            }

            $seen[$node] = true;
        }

        return false;
    }

    /**
     * True when following the parents from some node comes back to it.
     *
     * @param array<array-key, ?string> $parents key => parent key
     */
    public static function hasCycle(array $parents): bool
    {
        foreach (array_keys($parents) as $start) {
            $seen = [];

            $node = (string) $start;

            while (isset($parents[$node])) {
                if (isset($seen[$node])) {
                    return true;
                }

                $seen[$node] = true;
                $node        = $parents[$node];
            }
        }

        return false;
    }

    /**
     * @param array<array-key, array{parent: ?string, zone: string, order: int}> $nodes
     * @param list<string>                                                     $keys
     *
     * @return array<array-key, array{parent: ?string, zone: string, order: int}>
     */
    private static function renumber(array $nodes, array $keys): array
    {
        foreach ($keys as $i => $key) {
            $nodes[$key]['order'] = ($i + 1) * 10;
        }

        return $nodes;
    }
}
