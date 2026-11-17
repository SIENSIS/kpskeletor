<?php

declare(strict_types=1);

namespace Modules\Tasks\Tests;

use CodeIgniter\Events\Events;
use Modules\Core\Permissions\CrudPermissions;
use Modules\Core\Storage\StoredFile;
use Modules\Files\Policies\PolicyRegistry;
use Modules\Files\Tests\FilesTestCase;
use Modules\Tasks\Storage\AttachmentPolicy;
use Modules\Tasks\Storage\AttachmentUploads;

/**
 * Attachments of to-do tasks (tutorial 10): private files of `modules/tasks` served by
 * `files/private/{key}` follow the to-do task's visibility through `AttachmentPolicy` (default
 * deny, 404 when refused), and the form only accepts keys uploaded or chosen through its picker.
 *
 * @internal
 */
final class AttachmentsTest extends FilesTestCase
{
    use TasksFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        CrudPermissions::reset();
        // The Files tests remove the listeners of this event: register the module's policy again.
        Events::on(PolicyRegistry::EVENT, static function (PolicyRegistry $registry): void {
            $registry->register(new AttachmentPolicy());
        });
    }

    public function testThePolicyIsRegisteredForTheModuleFolder(): void
    {
        $policy = PolicyRegistry::instance()->for('modules/tasks/x.txt');

        $this->assertInstanceOf(AttachmentPolicy::class, $policy);
        $this->assertSame('hidden', $policy->browse());
    }

    public function testAPrivateToDoTasksFilesAreForWhoCanSeeIt(): void
    {
        $pau      = $this->createUser('pau@example.com', 'user');
        $stranger = $this->createUser('joan@example.com', 'user');
        $reader   = $this->createUser('reader@example.com', 'user');
        $reader->addPermission('tasks-tasks.show');
        $file = $this->attachment('pla.txt');
        $id   = $this->task('Privada', ['attachments' => $file->key], [(int) $pau->id]);
        AttachmentUploads::sync($id);
        $path = 'files/private/' . $file->key;

        $this->assertSame(404, $this->statusOf('GET', $path), 'Guest.');

        $this->login('joan@example.com');
        $this->assertSame(404, $this->statusOf('GET', $path), 'Neither assignee nor reader.');

        $this->login('pau@example.com');
        $this->assertSame(200, $this->statusOf('GET', $path), 'Assignee.');

        $this->login('reader@example.com');
        $this->assertSame(200, $this->statusOf('GET', $path), 'tasks-tasks.show.');

        $this->assertNotNull($stranger->id);
    }

    public function testAPublicToDoTasksFilesAreForEveryoneUntilItIsTrashed(): void
    {
        $file = $this->attachment('cartell.png', self::png());
        $id   = $this->task('Pública', ['visibility' => 'public', 'attachments' => $file->key]);
        AttachmentUploads::sync($id);
        $path = 'files/private/' . $file->key;

        $this->assertSame(200, $this->statusOf('GET', $path), 'Guests read the files of a public to-do task.');

        db_connect()->table('tasks')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $this->assertSame(404, $this->statusOf('GET', $path), 'Not once it is in the trash.');
    }

    public function testAFileNotAttachedYetIsOnlyForItsUploader(): void
    {
        $pau  = $this->createUser('pau@example.com', 'user');
        $this->createUser('joan@example.com', 'user');
        $file = $this->attachment('esborrany.txt', 'Text', (int) $pau->id);

        $this->login('joan@example.com');
        $this->assertSame(404, $this->statusOf('GET', 'files/private/' . $file->key));

        $this->login('pau@example.com');
        $this->assertSame(200, $this->statusOf('GET', 'files/private/' . $file->key));
    }

    public function testTheFormRefusesForgedKeysAndMirrorsTheAttachments(): void
    {
        $boss = $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $id      = $this->task('Amb adjunts');
        $code    = $this->taskCode($id);
        $foreign = service('storage')->storeContents('secret', 'secret.txt', 'private', 'shared/secret');
        $mine    = $this->attachment('meu.txt', 'Text', (int) $boss->id);

        // A file of another folder: refused, nothing saved.
        $this->send('POST', 'backend/tasks/tasks/edit/' . $code, $this->form(['attachments' => $foreign->key]))->assertOK();
        $this->assertNull($this->taskRow($id)['attachments']);

        // Its own upload (uploaded through the field's token in real use; the picker accepts it
        // because the user may choose it inside the field's folder).
        $this->send('POST', 'backend/tasks/tasks/edit/' . $code, $this->form(['attachments' => $mine->key]))->assertRedirect();
        $this->assertSame($mine->key, $this->taskRow($id)['attachments']);
        $this->assertSame([$mine->key], array_column(db_connect()->table('task_attachments')->where('task_id', $id)->get()->getResultArray(), 'file_key'));

        // Removed from the form: removed from the index.
        $this->send('POST', 'backend/tasks/tasks/edit/' . $code, $this->form(['attachments' => '']))->assertRedirect();
        $this->assertSame(0, db_connect()->table('task_attachments')->where('task_id', $id)->countAllResults());
    }

    private function attachment(string $name, string $contents = 'Text', ?int $uploader = null): StoredFile
    {
        $file = service('storage')->storeContents($contents, $name, 'private', AttachmentUploads::FOLDER);

        if ($uploader !== null) {
            db_connect()->table('stored_files')->where('id', $file->id)->update(['uploaded_by' => $uploader]);
            $file = service('storage')->find($file->key);
            $this->assertNotNull($file);
        }

        return $file;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function form(array $values): array
    {
        return $values + [
            'title' => 'Amb adjunts', 'description' => '', 'project_id' => '', 'status' => 'todo', 'priority' => 'normal',
            'labels' => [], 'due_on' => '', 'assignees' => [], 'visibility' => 'private', 'attachments' => '',
        ];
    }
}
