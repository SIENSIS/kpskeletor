<?php

declare(strict_types=1);

namespace Modules\Core\Security\Check;

use InvalidArgumentException;

/**
 * One result of `security:check`: a group (`configuration`, `secrets`, `installation`,
 * `accounts`, `dependencies`, `outbound`, `probe` or a module's own), a stable id
 * (`<group>.<what>`, used to notice a check that turns red), a status, what it means and how to
 * fix it (already translated, plain text).
 */
final class CheckResult
{
    public const OK      = 'ok';
    public const INFO    = 'info';
    public const WARNING = 'warning';
    public const ERROR   = 'error';

    /** Status => severity (higher is worse). */
    public const SEVERITY = [self::OK => 0, self::INFO => 1, self::WARNING => 2, self::ERROR => 3];

    public function __construct(
        public readonly string $group,
        public readonly string $id,
        public readonly string $status,
        public readonly string $title,
        public readonly string $message = '',
        public readonly string $fix = '',
    ) {
        if (! isset(self::SEVERITY[$status])) {
            throw new InvalidArgumentException('Unknown status: ' . $status);
        }
    }

    /**
     * @return array{group: string, id: string, status: string, title: string, message: string, fix: string}
     */
    public function toArray(): array
    {
        return [
            'group'   => $this->group,
            'id'      => $this->id,
            'status'  => $this->status,
            'title'   => $this->title,
            'message' => $this->message,
            'fix'     => $this->fix,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?self
    {
        $status = (string) ($data['status'] ?? '');

        if (! isset(self::SEVERITY[$status])) {
            return null;
        }

        return new self(
            (string) ($data['group'] ?? ''),
            (string) ($data['id'] ?? ''),
            $status,
            (string) ($data['title'] ?? ''),
            (string) ($data['message'] ?? ''),
            (string) ($data['fix'] ?? ''),
        );
    }
}
