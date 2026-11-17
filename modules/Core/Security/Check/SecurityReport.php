<?php

declare(strict_types=1);

namespace Modules\Core\Security\Check;

/**
 * The results of one `security:check` run, in the order the checks added them. Checks add
 * results with ok()/info()/warning()/error() (title, message and fix are language keys or
 * plain text already translated).
 */
final class SecurityReport
{
    /** Groups in the order they are shown; module groups follow. */
    public const GROUPS = ['configuration', 'secrets', 'installation', 'accounts', 'dependencies', 'outbound', 'probe'];

    /** @var list<CheckResult> */
    private array $results = [];

    public function __construct(public readonly string $checkedAt = '')
    {
    }

    public function add(CheckResult $result): self
    {
        $this->results[] = $result;

        return $this;
    }

    public function ok(string $group, string $id, string $title, string $message = ''): self
    {
        return $this->add(new CheckResult($group, $id, CheckResult::OK, $title, $message));
    }

    public function info(string $group, string $id, string $title, string $message = '', string $fix = ''): self
    {
        return $this->add(new CheckResult($group, $id, CheckResult::INFO, $title, $message, $fix));
    }

    public function warning(string $group, string $id, string $title, string $message = '', string $fix = ''): self
    {
        return $this->add(new CheckResult($group, $id, CheckResult::WARNING, $title, $message, $fix));
    }

    public function error(string $group, string $id, string $title, string $message = '', string $fix = ''): self
    {
        return $this->add(new CheckResult($group, $id, CheckResult::ERROR, $title, $message, $fix));
    }

    /**
     * @return list<CheckResult>
     */
    public function results(): array
    {
        return $this->results;
    }

    /**
     * Results by group, groups in the order of GROUPS (then the others alphabetically).
     *
     * @return array<string, list<CheckResult>>
     */
    public function grouped(): array
    {
        $groups = [];

        foreach ($this->results as $result) {
            $groups[$result->group][] = $result;
        }

        uksort($groups, static function (string $a, string $b): int {
            $ia = array_search($a, self::GROUPS, true);
            $ib = array_search($b, self::GROUPS, true);

            return [$ia === false ? 99 : $ia, $a] <=> [$ib === false ? 99 : $ib, $b];
        });

        return $groups;
    }

    /**
     * @return array{ok: int, info: int, warning: int, error: int}
     */
    public function counts(): array
    {
        $counts = [CheckResult::OK => 0, CheckResult::INFO => 0, CheckResult::WARNING => 0, CheckResult::ERROR => 0];

        foreach ($this->results as $result) {
            $counts[$result->status]++;
        }

        return $counts;
    }

    public function hasErrors(): bool
    {
        return $this->counts()[CheckResult::ERROR] > 0;
    }

    /**
     * The worst status of the report (`ok` when empty).
     */
    public function status(): string
    {
        $worst = CheckResult::OK;

        foreach ($this->results as $result) {
            if (CheckResult::SEVERITY[$result->status] > CheckResult::SEVERITY[$worst]) {
                $worst = $result->status;
            }
        }

        return $worst;
    }

    /**
     * Ids of the checks in error.
     *
     * @return list<string>
     */
    public function errorIds(): array
    {
        return array_values(array_unique(array_map(
            static fn (CheckResult $result): string => $result->id,
            array_filter($this->results, static fn (CheckResult $result): bool => $result->status === CheckResult::ERROR),
        )));
    }

    /**
     * @return array{checkedAt: string, counts: array{ok: int, info: int, warning: int, error: int}, results: list<array<string, string>>}
     */
    public function toArray(): array
    {
        return [
            'checkedAt' => $this->checkedAt,
            'counts'    => $this->counts(),
            'results'   => array_map(static fn (CheckResult $result): array => $result->toArray(), $this->results),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $report = new self((string) ($data['checkedAt'] ?? ''));

        foreach (is_array($data['results'] ?? null) ? $data['results'] : [] as $row) {
            $result = is_array($row) ? CheckResult::fromArray($row) : null;

            if ($result !== null) {
                $report->add($result);
            }
        }

        return $report;
    }
}
