<?php

declare(strict_types=1);

namespace Modules\Devtools\Packages;

use Modules\Core\Packages\PackageInfo;

/**
 * A built and validated package: the ZIP path, what the installer read from it, its SHA-256,
 * the files left out and the warnings (local changes not committed).
 */
final class BuildResult
{
    /**
     * @param list<string> $skipped  files left out (relative paths)
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly string $zip,
        public readonly PackageInfo $info,
        public readonly string $sha256,
        public readonly array $skipped = [],
        public readonly array $warnings = [],
    ) {
    }

    public function file(): string
    {
        return basename($this->zip);
    }

    /**
     * For the screen's flash data (no secrets: names, sizes, hashes).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'file'     => $this->file(),
            'name'     => $this->info->name,
            'type'     => $this->info->type,
            'version'  => $this->info->version,
            'files'    => count($this->info->files),
            'size'     => (int) filesize($this->zip),
            'sha256'   => $this->sha256,
            'skipped'  => $this->skipped,
            'warnings' => $this->warnings,
        ];
    }
}
