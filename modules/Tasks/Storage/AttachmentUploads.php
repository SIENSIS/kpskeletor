<?php

declare(strict_types=1);

namespace Modules\Tasks\Storage;

use CodeIgniter\I18n\Time;
use Modules\Core\Storage\CrudUploadHandler;
use Modules\Core\Storage\Picker\PickerField;
use Modules\Core\Storage\StoredFile;

/**
 * The attachments of a to-do task (tutorial 10): a file-picker field (upload or choose, up to
 * MAX_FILES files) on the **private** disk, folder `modules/tasks`. The keys are stored comma
 * separated in `tasks.attachments` and mirrored in `task_attachments` (`sync()`), which the
 * folder's `AttachmentPolicy` reads to decide who downloads a file.
 */
final class AttachmentUploads extends CrudUploadHandler
{
    public const FOLDER    = 'modules/tasks';
    public const TYPES     = 'pdf,txt,csv,jpg,jpeg,png,webp,odt,ods,docx,xlsx';
    public const MAX_SIZE  = 10 * 1024 * 1024;
    public const MAX_FILES = 5;

    protected function folder(): string
    {
        return self::FOLDER;
    }

    protected function disk(): string
    {
        return 'private';
    }

    public static function pickerField(string $name): PickerField
    {
        return parent::pickerField($name)->maxFiles(self::MAX_FILES);
    }

    /**
     * Mirrors the attachment keys of a to-do task into `task_attachments`.
     */
    public static function sync(int $taskId): void
    {
        $db   = db_connect();
        $row  = $db->table('tasks')->select('attachments')->where('id', $taskId)->get()->getRowArray();
        $keys = is_array($row) ? PickerField::keys(is_string($row['attachments']) ? $row['attachments'] : null) : [];

        $db->table('task_attachments')->where('task_id', $taskId)->groupStart()->whereNotIn('file_key', $keys === [] ? [''] : $keys)->groupEnd()->delete();

        $known = array_column($db->table('task_attachments')->select('file_key')->where('task_id', $taskId)->get()->getResultArray(), 'file_key');
        $now   = Time::now()->toDateTimeString();

        foreach (array_diff($keys, $known) as $key) {
            $db->table('task_attachments')->insert(['task_id' => $taskId, 'file_key' => $key, 'created_at' => $now]);
        }
    }

    /**
     * The files of a to-do task, for its details and the API.
     *
     * @return list<array{key: string, name: string, size: int, url: string}>
     */
    public static function files(?string $value): array
    {
        $list = [];

        foreach (PickerField::keys($value) as $key) {
            $file = service('storage')->find($key);

            if ($file instanceof StoredFile) {
                $list[] = ['key' => $file->key, 'name' => $file->originalName, 'size' => $file->size, 'url' => service('storage')->url($file, true)];
            }
        }

        return $list;
    }
}
