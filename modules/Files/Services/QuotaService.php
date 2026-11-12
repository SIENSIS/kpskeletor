<?php

declare(strict_types=1);

namespace Modules\Files\Services;

use RuntimeException;

/**
 * Quotas (docs/files.md, "Quotas"): every folder of the chain with a quota limits the total size
 * of the files below it (the trash included, until it is emptied). A personal space without its
 * own quota uses `Files.personalQuota` (MB; 0 = no limit).
 */
final class QuotaService
{
    public function __construct(private readonly FolderRepository $folders = new FolderRepository())
    {
    }

    /**
     * The limits that apply to a folder: limiting folder path => bytes.
     *
     * @return array<string, int>
     */
    public function limits(string $disk, string $path): array
    {
        $limits = [];

        foreach (Spaces::chain($path) as $folder) {
            $found = $this->folders->find($disk, $folder);
            $quota = $found['quota'] ?? null;

            if ($quota === null && $disk === 'private' && preg_match('#^users/[1-9][0-9]*$#', $folder) === 1) {
                $megabytes = (int) setting('Files.personalQuota');
                $quota     = $megabytes > 0 ? $megabytes * 1024 * 1024 : null;
            }

            if ($quota !== null) {
                $limits[$folder] = $quota;
            }
        }

        return $limits;
    }

    /**
     * Bytes used below a folder (every file with a `files` row, the trash included).
     */
    public function used(string $disk, string $path): int
    {
        $row = db_connect()->table('files')
            ->selectSum('stored_files.size', 'total')
            ->join('stored_files', 'stored_files.id = files.stored_file_id')
            ->where('files.disk', $disk)
            ->groupStart()->where('files.folder', $path)->orLike('files.folder', $path . '/', 'after')->groupEnd()
            ->get()->getRowArray();

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Whether adding bytes to a folder keeps every quota of its chain.
     */
    public function allows(string $disk, string $path, int $bytes): bool
    {
        foreach ($this->limits($disk, $path) as $folder => $limit) {
            if ($this->used($disk, $folder) + $bytes > $limit) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws RuntimeException with the translated message when a quota would be exceeded
     */
    public function check(string $disk, string $path, int $bytes): void
    {
        if (! $this->allows($disk, $path, $bytes)) {
            throw new RuntimeException(lang('Files.errors.quota'));
        }
    }

    /**
     * The nearest quota of a folder for display: used and limit in bytes, or null.
     *
     * @return array{used: int, limit: int}|null
     */
    public function summary(string $disk, string $path): ?array
    {
        foreach ($this->limits($disk, $path) as $folder => $limit) {
            return ['used' => $this->used($disk, $folder), 'limit' => $limit];
        }

        return null;
    }
}
