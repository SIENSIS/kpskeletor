<?php

declare(strict_types=1);

namespace Modules\Files\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Modules\Core\Storage\StoredFile;

/**
 * The file manager's files (`files` joined with `stored_files`): listing with search and filters,
 * metadata and moves. A file's folder is virtual (`files.folder`): moving a file never touches
 * the disk.
 *
 * @phpstan-type FileRow array<string, mixed>
 */
final class FileRepository
{
    /** Type filters: name => extensions. */
    public const TYPES = [
        'image'       => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
        'pdf'         => ['pdf'],
        'document'    => ['txt', 'csv', 'odt', 'ods', 'odp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'rtf', 'md'],
        'audio_video' => ['mp3', 'ogg', 'wav', 'mp4', 'webm', 'mov'],
        'archive'     => ['zip', 'gz', 'tar', '7z'],
    ];

    public const MAX_TAGS = 10;

    /**
     * Registers a stored file in a folder.
     */
    public function create(StoredFile $stored, string $folder, ?string $thumbnail = null): int
    {
        $now = Time::now()->toDateTimeString();
        $this->db()->table('files')->insert([
            'stored_file_id' => $stored->id,
            'disk'           => $stored->disk,
            'folder'         => $folder,
            'name'           => $stored->originalName,
            'visibility'     => $stored->disk === 'public' ? 'public' : 'private',
            'alt'            => '',
            'tags'           => '',
            'thumbnail'      => $thumbnail,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        return (int) $this->db()->insertID();
    }

    /**
     * @return FileRow|null
     */
    public function find(int $id): ?array
    {
        $row = $this->select()->where('files.id', $id)->get()->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * @return FileRow|null
     */
    public function findByStoredFile(int $storedId): ?array
    {
        $row = $this->select()->where('files.stored_file_id', $storedId)->get()->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * The files of one folder (not in the trash), filtered.
     *
     * @param array{q?: string, type?: string, from?: string, to?: string, tag?: string, sort?: string} $filters
     *
     * @return list<FileRow>
     */
    public function inFolder(string $disk, string $folder, array $filters = []): array
    {
        $builder = $this->select()->where('files.disk', $disk)->where('files.folder', $folder)->where('files.deleted_at', null);
        $this->filter($builder, $filters);

        return $builder->get()->getResultArray();
    }

    /**
     * Trashed files of the given folders (the caller passes the folders the user may delete in).
     *
     * @param array<string, list<string>> $folders disk => folders
     *
     * @return list<FileRow>
     */
    public function trashed(array $folders): array
    {
        $rows = [];

        foreach ($folders as $disk => $paths) {
            if ($paths === []) {
                continue;
            }

            foreach ($this->select()->where('files.disk', $disk)->whereIn('files.folder', $paths)->where('files.deleted_at IS NOT NULL')->orderBy('files.deleted_at', 'desc')->get()->getResultArray() as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Files the module code stored in a module folder (no `files` row): read-only listing.
     *
     * @return list<array<string, mixed>>
     */
    public function storedIn(string $disk, string $folder): array
    {
        $rows = $this->db()->table('stored_files')->where('disk', $disk)->like('path', $folder . '/', 'after')->orderBy('created_at', 'desc')->get()->getResultArray();

        return array_values(array_filter($rows, static fn (array $row): bool => Spaces::parent((string) $row['path']) === $folder));
    }

    /**
     * @param array{name?: string, alt?: string, tags?: string} $values
     */
    public function update(int $id, array $values): void
    {
        $this->db()->table('files')->where('id', $id)->update($values + ['updated_at' => Time::now()->toDateTimeString()]);
    }

    public function move(int $id, string $folder): void
    {
        $this->update($id, ['folder' => $folder]);
    }

    /**
     * Tags as stored: lower case, trimmed, unique, at most MAX_TAGS of 30 characters.
     */
    public static function normaliseTags(string $tags): string
    {
        $list = [];

        foreach (explode(',', $tags) as $tag) {
            $tag = mb_strtolower(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $tag)));

            if ($tag !== '' && mb_strlen($tag) <= 30 && ! in_array($tag, $list, true)) {
                $list[] = $tag;
            }
        }

        return implode(',', array_slice($list, 0, self::MAX_TAGS));
    }

    /**
     * @param BaseBuilder<BaseConnection<object|resource, object|resource>>                          $builder
     * @param array{q?: string, type?: string, from?: string, to?: string, tag?: string, sort?: string} $filters
     */
    private function filter(BaseBuilder $builder, array $filters): void
    {
        $q = trim($filters['q'] ?? '');

        if ($q !== '') {
            $builder->groupStart()->like('files.name', $q)->orLike('files.alt', $q)->orLike('files.tags', $q)->groupEnd();
        }

        $type = $filters['type'] ?? '';

        if ($type === 'other') {
            $builder->whereNotIn('stored_files.extension', array_merge(...array_values(self::TYPES)));
        } elseif (isset(self::TYPES[$type])) {
            $builder->whereIn('stored_files.extension', self::TYPES[$type]);
        }

        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            $date = $filters[$key] ?? '';

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
                $builder->where('files.created_at ' . $operator, $date . ($key === 'from' ? ' 00:00:00' : ' 23:59:59'));
            }
        }

        $tag = mb_strtolower(trim($filters['tag'] ?? ''));

        if ($tag !== '') {
            // Exact tag inside the comma-separated list.
            $builder->groupStart()
                ->where('files.tags', $tag)
                ->orLike('files.tags', $tag . ',', 'after')
                ->orLike('files.tags', ',' . $tag, 'before')
                ->orLike('files.tags', ',' . $tag . ',')
                ->groupEnd();
        }

        match ($filters['sort'] ?? 'name') {
            'date'  => $builder->orderBy('files.created_at', 'desc'),
            'size'  => $builder->orderBy('stored_files.size', 'desc'),
            default => $builder->orderBy('files.name', 'asc'),
        };
    }

    /**
     * @return BaseBuilder<BaseConnection<object|resource, object|resource>>
     */
    private function select(): BaseBuilder
    {
        return $this->db()->table('files')
            ->select('files.*, stored_files.file_key, stored_files.path, stored_files.original_name, stored_files.extension, stored_files.mime, stored_files.size, stored_files.checksum, stored_files.width, stored_files.height, stored_files.uploaded_by, stored_files.created_at AS stored_at')
            ->join('stored_files', 'stored_files.id = files.stored_file_id');
    }

    /**
     * @return BaseConnection<object|resource, object|resource>
     */
    private function db(): BaseConnection
    {
        return db_connect();
    }
}
