<?php

declare(strict_types=1);

namespace Modules\Core\Routing;

/**
 * Passed with the Core event `backendPathChanged` while `php spark app:backend-path` runs (in
 * maintenance mode, after `.env` was rewritten): a module that stores internal paths rewrites
 * those that still carry the old prefix with `rewrite()` / `rewriteJson()` and reports how many
 * rows it changed with `changed()`.
 *
 * Stored links use the canonical prefix `backend` (D-40-02), so a module that stores them
 * through `backend_link()` / `BackendPath::store()` has nothing to do; the rewrite covers values
 * written with the old prefix itself.
 */
final class BackendPathChanged
{
    /** @var array<string, int> what => rows changed */
    private array $changed = [];

    public function __construct(public readonly string $old, public readonly string $new)
    {
    }

    /**
     * A stored path under the old prefix in its stored form (canonical prefix); anything else
     * unchanged.
     */
    public function rewrite(string $value): string
    {
        return BackendPath::store($value, $this->old);
    }

    /**
     * A JSON document with every string value rewritten; null or invalid JSON unchanged.
     */
    public function rewriteJson(?string $json): ?string
    {
        if ($json === null || $json === '') {
            return $json;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $json;
        }

        $changed = false;
        $walk    = function (mixed $value) use (&$walk, &$changed): mixed {
            if (is_array($value)) {
                return array_map($walk, $value);
            }

            // A bare word equal to the old prefix is not a path (e.g. a group named "admin").
            if (is_string($value) && $value !== $this->old) {
                $new     = $this->rewrite($value);
                $changed = $changed || $new !== $value;

                return $new;
            }

            return $value;
        };
        $data = $walk($data);

        return $changed ? json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : $json;
    }

    /**
     * Rewrites one column of a table (plain paths or JSON) and records the rows changed.
     *
     * @param array<string, mixed> $where only the rows matching these conditions (e.g. `['type' => 'url']`)
     */
    public function rewriteColumn(string $table, string $column, bool $json = false, array $where = [], string $key = 'id'): int
    {
        $db = db_connect();

        if (! $db->tableExists($table)) {
            return 0;
        }

        $count = 0;

        foreach ($db->table($table)->select([$key, $column])->where($where)->get()->getResultArray() as $row) {
            $value = $row[$column] === null ? null : (string) $row[$column];
            $new   = $json ? $this->rewriteJson($value) : ($value === null ? null : $this->rewrite($value));

            if ($new !== $value) {
                $db->table($table)->where($key, $row[$key])->update([$column => $new]);
                $count++;
            }
        }

        $this->changed($table . '.' . $column, $count);

        return $count;
    }

    public function changed(string $what, int $rows): void
    {
        $this->changed[$what] = ($this->changed[$what] ?? 0) + $rows;
    }

    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        return $this->changed;
    }
}
