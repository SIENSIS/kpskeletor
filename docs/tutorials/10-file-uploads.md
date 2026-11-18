# Tutorial 10 — Uploading files in a form

To-do tasks get **attachments**: up to five files (PDF, office documents, images…) uploaded or
chosen with the Core's **file picker**, stored on the **private disk** in the module's folder,
and served only to the users who may see the to-do task, through a **`FileAccessPolicy`**.

Finished code:

| Piece | File |
|---|---|
| Upload handler (folder, disk, types, size) | `modules/Tasks/Storage/AttachmentUploads.php` (extends `CrudUploadHandler`) |
| The CRUD field | `modules/Tasks/Controllers/Tasks.php` (`callbackField('attachments', …)`, `beforeSave()`) |
| Index of attachments | table `task_attachments` (`Database/Migrations/2026-11-17-100005_CreateTaskAttachments.php`) |
| Access policy | `modules/Tasks/Storage/AttachmentPolicy.php`, registered in `modules/Tasks/Config/Events.php` |
| Backup | `$backup = ['files' => ['modules/tasks']]` in the manifest |

References: [`files.md`](../files.md) ("File picker", "FileAccessPolicy") and
[`storage.md`](../storage.md).

## 1. Never handle uploads yourself

Files are stored only through the Storage service (`service('storage')`), which applies the
upload rules (allow-listed types checked by content, size, random names, no SVG or HTML by
default, images re-encoded). A form field never takes a raw upload: it uses the **file picker**,
which uploads through a signed token that fixes the folder, disk, types and size on the server,
so the browser cannot change them.

## 2. The upload handler

A module describes its field once, in a class that extends
`Modules\Core\Storage\CrudUploadHandler`:

```php
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
}
```

- The folder is the module's own, `modules/<module>`.
- `private`: the files are in `writable/storage`, never served directly by the web server, only
  through `files/private/{key}` after an access check.
- The types are an allow-list; the Storage service still checks the real content.

## 3. The field in the CRUD

The column `tasks.attachments` holds the keys, comma separated, as the picker posts them. The
CRUD draws the picker instead of a text input, shows links in the list, and validates the
posted value:

```php
->callbackField('attachments', static fn ($value, ?array $row, array $field): string => AttachmentUploads::renderPicker('attachments', $value, $field))
->callbackColumn('attachments', static fn ($value): string => self::attachmentLinks(is_string($value) ? $value : null))
->setRules(['attachments' => 'permit_empty|max_length[200]', /* … */])
```

In `beforeInsert()`/`beforeUpdate()` (the controller's `beforeSave()`), a key is accepted only if
it is kept from the current value or came through the field (uploaded or chosen with this field's
token); anything else, such as the key of someone else's file, refuses the save:

```php
if (array_key_exists('attachments', $data)) {
    $current             = is_string($old['attachments'] ?? null) ? $old['attachments'] : null;
    $data['attachments'] = is_string($data['attachments']) && trim($data['attachments']) !== '' ? trim($data['attachments']) : null;

    if (! AttachmentUploads::accepts('attachments', $data['attachments'], $current)) {
        return lang('Core.picker.errors.value');
    }
}
```

After the write, the `afterWrite()` hook of tutorial 2 calls `AttachmentUploads::sync($taskId)`,
which mirrors the keys into `task_attachments` (one row per file): the policy reads that table.

## 4. Who downloads a file: a `FileAccessPolicy`

Without a policy, files of `modules/tasks` would follow the Files module's folder rules. Their
access must follow the **to-do task** instead, so the module registers a policy for its folder
(`Config/Events.php`):

```php
Events::on(PolicyRegistry::EVENT, static function (PolicyRegistry $registry): void {
    $registry->register(new AttachmentPolicy());
});
```

```php
final class AttachmentPolicy implements FileAccessPolicy
{
    public function folder(): string
    {
        return AttachmentUploads::FOLDER;
    }

    public function browse(): string
    {
        return 'hidden';        // not listed in the file manager
    }

    public function canView(?User $user, StoredFile $file): bool
    {
        // the to-do tasks the file is attached to (task_attachments)…
        if ($tasks === []) {
            // not attached yet (just uploaded through the picker): only its uploader
            return $user !== null && $file->uploadedBy !== null && (int) $file->uploadedBy === (int) $user->id;
        }

        foreach ($tasks as $task) {
            if ($task['deleted_at'] === null && $task['visibility'] === 'public') {
                return true;    // a public to-do task: everyone, guests too
            }

            if ($user !== null && $repository->canSee($user, $task)) {
                return true;    // tasks-tasks.show, its creator or an assignee
            }
        }

        return false;           // default deny: a 404
    }
}
```

The rule "who sees a to-do task" is `TaskRepository::canSee()`, the same one the board uses: one
rule, one place. A refused file answers 404, so its existence is not revealed. Only policies of
enabled modules count.

## 5. Backups

Files are not in the database. Declare the folder in the manifest so backups include it:

```php
public array $backup = ['files' => ['modules/tasks']];
```

## Try it

Edit a demo to-do task in `backend/tasks/tasks`, upload a PDF in "Attachments" and save. The list
links to it (`files/private/<key>`). Open that link in a private window: 404 for a private to-do
task; make the to-do task public and it opens. Sign in as a demo user who is not assigned: 404.

## Tests

`modules/Tasks/Tests/AttachmentsTest.php`:

- `testThePolicyIsRegisteredForTheModuleFolder`
- `testAPrivateToDoTasksFilesAreForWhoCanSeeIt` (assignee, creator and `tasks-tasks.show` yes;
  other users and guests 404)
- `testAPublicToDoTasksFilesAreForEveryoneUntilItIsTrashed`
- `testAFileNotAttachedYetIsOnlyForItsUploader`
- `testTheFormRefusesForgedKeysAndMirrorsTheAttachments` (a key from another folder is refused
  and nothing is saved; removing a file removes it from the index)

Files are created in tests with `service('storage')->storeContents($contents, $name, 'private',
AttachmentUploads::FOLDER)`; no real upload is needed.

```bash
vendor/bin/phpunit modules/Tasks/Tests/AttachmentsTest.php
```

Next: [tutorial 11](11-json-api.md), the JSON API.
