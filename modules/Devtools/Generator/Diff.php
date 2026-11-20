<?php

declare(strict_types=1);

namespace Modules\Devtools\Generator;

/**
 * A line diff for the preview (longest common subsequence; the files are small): the lines of
 * the new content marked `add`, `del` or `same`, with unchanged stretches longer than the context
 * folded into one `skip` line.
 */
final class Diff
{
    /**
     * @return list<array{type: string, text: string}>
     */
    public static function lines(string $old, string $new, int $context = 3): array
    {
        $a = explode("\n", $old);
        $b = explode("\n", $new);
        $n = count($a);
        $m = count($b);

        // Common prefix and suffix first: most changes are small insertions.
        $start = 0;

        while ($start < $n && $start < $m && $a[$start] === $b[$start]) {
            $start++;
        }

        $end = 0;

        while ($end < $n - $start && $end < $m - $start && $a[$n - 1 - $end] === $b[$m - 1 - $end]) {
            $end++;
        }

        $midA = array_slice($a, $start, $n - $start - $end);
        $midB = array_slice($b, $start, $m - $start - $end);
        $ops  = [];

        foreach (array_slice($a, 0, $start) as $line) {
            $ops[] = ['type' => 'same', 'text' => $line];
        }

        foreach (self::lcs($midA, $midB) as $op) {
            $ops[] = $op;
        }

        foreach (array_slice($a, $n - $end) as $line) {
            $ops[] = ['type' => 'same', 'text' => $line];
        }

        return self::fold($ops, $context);
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     *
     * @return list<array{type: string, text: string}>
     */
    private static function lcs(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);

        if ($n * $m > 4_000_000) {
            return [...array_map(static fn (string $l): array => ['type' => 'del', 'text' => $l], $a), ...array_map(static fn (string $l): array => ['type' => 'add', 'text' => $l], $b)];
        }

        $table = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $table[$i][$j] = $a[$i] === $b[$j] ? $table[$i + 1][$j + 1] + 1 : max($table[$i + 1][$j], $table[$i][$j + 1]);
            }
        }

        $ops = [];
        $i   = 0;
        $j   = 0;

        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $ops[] = ['type' => 'same', 'text' => $a[$i]];
                $i++;
                $j++;
            } elseif ($table[$i + 1][$j] >= $table[$i][$j + 1]) {
                $ops[] = ['type' => 'del', 'text' => $a[$i++]];
            } else {
                $ops[] = ['type' => 'add', 'text' => $b[$j++]];
            }
        }

        while ($i < $n) {
            $ops[] = ['type' => 'del', 'text' => $a[$i++]];
        }

        while ($j < $m) {
            $ops[] = ['type' => 'add', 'text' => $b[$j++]];
        }

        return $ops;
    }

    /**
     * @param list<array{type: string, text: string}> $ops
     *
     * @return list<array{type: string, text: string}>
     */
    private static function fold(array $ops, int $context): array
    {
        $keep  = [];
        $count = count($ops);

        foreach ($ops as $i => $op) {
            if ($op['type'] !== 'same') {
                for ($k = max(0, $i - $context); $k <= min($count - 1, $i + $context); $k++) {
                    $keep[$k] = true;
                }
            }
        }

        $result  = [];
        $skipped = 0;

        foreach ($ops as $i => $op) {
            if (isset($keep[$i])) {
                if ($skipped > 0) {
                    $result[] = ['type' => 'skip', 'text' => (string) $skipped];
                    $skipped  = 0;
                }

                $result[] = $op;
            } else {
                $skipped++;
            }
        }

        if ($skipped > 0) {
            $result[] = ['type' => 'skip', 'text' => (string) $skipped];
        }

        return $result;
    }
}
