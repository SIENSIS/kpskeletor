<?php

declare(strict_types=1);

namespace Modules\Tasks\Storage;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Storage\StoredFile;
use Modules\Files\Policies\FileAccessPolicy;
use Modules\Tasks\Services\TaskRepository;

/**
 * Who reads the private files of `modules/tasks` (tutorial 10): they follow the visibility of the
 * to-do task they are attached to (`task_attachments`), not the folder ACL. Registered in
 * `Config/Events.php` (`filesAccessPolicies`). Default deny:
 *
 * - attached to a **public** to-do task (not in the trash): anyone, guests too;
 * - attached to a private one: a signed-in user who may see it (`tasks-tasks.show`, its creator
 *   or one of its assignees);
 * - not attached yet (just uploaded through the picker): only the user who uploaded it.
 */
final class AttachmentPolicy implements FileAccessPolicy
{
    public function folder(): string
    {
        return AttachmentUploads::FOLDER;
    }

    public function browse(): string
    {
        return 'hidden';
    }

    public function canView(?User $user, StoredFile $file): bool
    {
        $db    = db_connect();
        $tasks = $db->table('tasks')->select('tasks.id, tasks.visibility, tasks.created_by, tasks.deleted_at')
            ->join('task_attachments', 'task_attachments.task_id = tasks.id')
            ->where('task_attachments.file_key', $file->key)
            ->get()->getResultArray();

        if ($tasks === []) {
            return $user !== null && $file->uploadedBy !== null && (int) $file->uploadedBy === (int) $user->id;
        }

        $repository = new TaskRepository();

        foreach ($tasks as $task) {
            if ($task['deleted_at'] === null && $task['visibility'] === 'public') {
                return true;
            }

            if ($user !== null && $repository->canSee($user, $task)) {
                return true;
            }
        }

        return false;
    }
}
