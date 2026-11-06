<?php

declare(strict_types=1);

namespace Modules\Core\Scheduler;

/**
 * Outcome of a scheduled task run: success, failed or skipped, with a short output (shown in the
 * scheduled tasks screen, never secrets).
 */
final class TaskResult
{
    public const SUCCESS = 'success';
    public const FAILED  = 'failed';
    public const SKIPPED = 'skipped';

    /** Longest output stored, in bytes. */
    public const MAX_OUTPUT = 60_000;

    private function __construct(
        public readonly string $status,
        public readonly string $output,
    ) {
    }

    public static function success(string $output = ''): self
    {
        return new self(self::SUCCESS, $output);
    }

    public static function failure(string $output = ''): self
    {
        return new self(self::FAILED, $output);
    }

    public static function skipped(string $output = ''): self
    {
        return new self(self::SKIPPED, $output);
    }

    public function failed(): bool
    {
        return $this->status === self::FAILED;
    }

    /**
     * The same result with more output appended (e.g. what the task printed).
     */
    public function withOutput(string $extra): self
    {
        $extra = trim($extra);

        if ($extra === '') {
            return $this;
        }

        return new self($this->status, trim($this->output . "\n" . $extra));
    }

    /** The output cut to MAX_OUTPUT bytes (valid UTF-8). */
    public function storedOutput(): string
    {
        if (strlen($this->output) <= self::MAX_OUTPUT) {
            return $this->output;
        }

        return mb_strcut($this->output, 0, self::MAX_OUTPUT, 'UTF-8') . "\n…";
    }
}
