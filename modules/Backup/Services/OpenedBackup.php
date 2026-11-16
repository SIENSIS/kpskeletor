<?php

declare(strict_types=1);

namespace Modules\Backup\Services;

use Modules\Core\Archive\SafeZip;

/**
 * A backup decrypted, authenticated and checked (BackupService::open()): its manifest and the
 * ZIP in a private temporary folder. Always call close(): it deletes the decrypted copy.
 */
final class OpenedBackup
{
    /**
     * @param array<string, mixed> $manifest
     */
    public function __construct(
        public readonly string $file,
        public readonly array $manifest,
        public readonly SafeZip $zip,
        public readonly string $workFolder,
    ) {
    }

    public function type(): string
    {
        return (string) ($this->manifest['type'] ?? 'full');
    }

    public function hasDatabase(): bool
    {
        return (bool) ($this->manifest['contents']['database'] ?? false);
    }

    /**
     * @return list<string> file areas (storage disks and `writable-uploads`)
     */
    public function fileAreas(): array
    {
        $areas = $this->manifest['contents']['files'] ?? [];

        return is_array($areas) ? array_values(array_filter($areas, 'is_string')) : [];
    }

    public function env(): ?string
    {
        return $this->zip->has(BackupService::ENV_ENTRY) ? $this->zip->read(BackupService::ENV_ENTRY, 1_048_576) : null;
    }

    public function close(): void
    {
        $this->zip->close();
        BackupService::removeFolder($this->workFolder);
    }
}
