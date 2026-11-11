<?php

declare(strict_types=1);

namespace Modules\Files\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Modules\Core\Storage\PathNormalizer;
use RuntimeException;

/**
 * The registered folders (`folders`) and their ACL (`folder_permissions`). Paths are always
 * normalised (PathNormalizer); a folder is identified by its disk and path.
 *
 * @phpstan-type Folder array{id: int, disk: string, path: string, owner_id: int|null, quota: int|null, own_permissions: bool}
 * @phpstan-type Entry array{subject_type: string, subject_id: string, rights: list<string>}
 */
final class FolderRepository
{
    public const SUBJECTS = ['group', 'user', 'authenticated'];

    /** @var array<string, array<string, mixed>|null> */
    private array $cache = [];

    /**
     * @return Folder|null
     */
    public function find(string $disk, string $path): ?array
    {
        $key = $disk . ':' . $path;

        if (! array_key_exists($key, $this->cache)) {
            $row               = $this->db()->table('folders')->where('disk', $disk)->where('path', $path)->get()->getRowArray();
            $this->cache[$key] = is_array($row) ? $row : null;
        }

        return $this->cache[$key] === null ? null : self::normalise($this->cache[$key]);
    }

    /**
     * Every registered folder of a disk below a path (the path itself included), by path.
     *
     * @return array<string, Folder>
     */
    public function below(string $disk, string $path): array
    {
        $rows = $this->db()->table('folders')->where('disk', $disk)
            ->groupStart()->where('path', $path)->orLike('path', $path . '/', 'after')->groupEnd()
            ->orderBy('path')->get()->getResultArray();

        $folders = [];

        foreach ($rows as $row) {
            $folders[(string) $row['path']] = self::normalise($row);
        }

        return $folders;
    }

    /**
     * Registers a folder (idempotent): returns the existing one when there is one.
     *
     * @param array{owner_id?: int|null, quota?: int|null, own_permissions?: bool, created_by?: int|null} $values
     *
     * @return Folder
     */
    public function ensure(string $disk, string $path, array $values = []): array
    {
        $path = PathNormalizer::normalize($path);

        if (($found = $this->find($disk, $path)) !== null) {
            return $found;
        }

        $now = Time::now()->toDateTimeString();
        $this->db()->table('folders')->insert([
            'disk'            => $disk,
            'path'            => $path,
            'owner_id'        => $values['owner_id'] ?? null,
            'quota'           => $values['quota'] ?? null,
            'own_permissions' => ($values['own_permissions'] ?? false) ? 1 : 0,
            'created_by'      => $values['created_by'] ?? null,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
        unset($this->cache[$disk . ':' . $path]);

        return $this->find($disk, $path) ?? throw new RuntimeException('Folder not saved.');
    }

    /**
     * Renames a folder: its path, the paths of the folders below it and the folder of their files.
     */
    public function rename(string $disk, string $from, string $to): void
    {
        $db = $this->db();
        $db->transStart();

        foreach ($this->below($disk, $from) as $path => $folder) {
            $db->table('folders')->where('id', $folder['id'])->update(['path' => $to . substr($path, strlen($from)), 'updated_at' => Time::now()->toDateTimeString()]);
        }

        foreach ($db->table('files')->select('id, folder')->where('disk', $disk)
            ->groupStart()->where('folder', $from)->orLike('folder', $from . '/', 'after')->groupEnd()
            ->get()->getResultArray() as $file) {
            $db->table('files')->where('id', $file['id'])->update(['folder' => $to . substr((string) $file['folder'], strlen($from))]);
        }

        $db->transComplete();
        $this->cache = [];
    }

    public function delete(int $id): void
    {
        $this->db()->table('folder_permissions')->where('folder_id', $id)->delete();
        $this->db()->table('folders')->where('id', $id)->delete();
        $this->cache = [];
    }

    public function setQuota(int $id, ?int $bytes): void
    {
        $this->db()->table('folders')->where('id', $id)->update(['quota' => $bytes, 'updated_at' => Time::now()->toDateTimeString()]);
        $this->cache = [];
    }

    /**
     * The ACL entries of a folder.
     *
     * @return list<Entry>
     */
    public function permissions(int $folderId): array
    {
        $entries = [];

        foreach ($this->db()->table('folder_permissions')->where('folder_id', $folderId)->orderBy('subject_type')->orderBy('subject_id')->get()->getResultArray() as $row) {
            $entries[] = [
                'subject_type' => (string) $row['subject_type'],
                'subject_id'   => (string) $row['subject_id'],
                'rights'       => Rights::parse((string) $row['rights']),
            ];
        }

        return $entries;
    }

    /**
     * Replaces the ACL of a folder. `$own` false: the folder inherits its parent's again (the
     * entries are removed).
     *
     * @param list<Entry> $entries
     */
    public function savePermissions(int $folderId, bool $own, array $entries): void
    {
        $db = $this->db();
        $db->transStart();
        $db->table('folder_permissions')->where('folder_id', $folderId)->delete();

        if ($own) {
            foreach ($entries as $entry) {
                $db->table('folder_permissions')->insert([
                    'folder_id'    => $folderId,
                    'subject_type' => $entry['subject_type'],
                    'subject_id'   => $entry['subject_id'],
                    'rights'       => Rights::format($entry['rights']),
                ]);
            }
        }

        $db->table('folders')->where('id', $folderId)->update(['own_permissions' => $own ? 1 : 0, 'updated_at' => Time::now()->toDateTimeString()]);
        $db->transComplete();
        $this->cache = [];
    }

    /**
     * Whether something is inside a folder: files (the trash included) or subfolders.
     */
    public function isEmpty(string $disk, string $path): bool
    {
        $files = $this->db()->table('files')->where('disk', $disk)
            ->groupStart()->where('folder', $path)->orLike('folder', $path . '/', 'after')->groupEnd()
            ->countAllResults();

        return $files === 0 && count($this->below($disk, $path)) <= 1;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return Folder
     */
    private static function normalise(array $row): array
    {
        return [
            'id'              => (int) $row['id'],
            'disk'            => (string) $row['disk'],
            'path'            => (string) $row['path'],
            'owner_id'        => isset($row['owner_id']) ? (int) $row['owner_id'] : null,
            'quota'           => isset($row['quota']) ? (int) $row['quota'] : null,
            'own_permissions' => (bool) $row['own_permissions'],
        ];
    }

    /**
     * @return BaseConnection<object|resource, object|resource>
     */
    private function db(): BaseConnection
    {
        return db_connect();
    }
}
