<?php

declare(strict_types=1);

namespace Modules\Backup\Services;

/**
 * What a restore did: the parts restored, the safety backup made first, and the `.env` situation
 * (the secrets of the backup that differ from the current `.env` and the merged content).
 */
final class RestoreResult
{
    /**
     * @param list<string>         $parts         `database`, `files`
     * @param list<string>         $envDifferences secret keys whose value differs
     * @param array<string, mixed> $manifest
     */
    public function __construct(
        public readonly string $file,
        public readonly array $parts,
        public readonly ?string $safetyFile,
        public readonly array $envDifferences,
        public readonly ?string $mergedEnv,
        public readonly ?string $backupEnv,
        public readonly array $manifest,
    ) {
    }
}
