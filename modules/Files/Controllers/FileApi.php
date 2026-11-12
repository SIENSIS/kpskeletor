<?php

declare(strict_types=1);

namespace Modules\Files\Controllers;

use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Controllers\PrivateFiles;
use Modules\Core\Groups\GroupRegistry;
use Modules\Core\Permissions\Authorization;
use Modules\Core\Storage\PathNormalizer;
use Modules\Core\Storage\Storage;
use Modules\Core\Storage\StorageException;
use Modules\Core\Storage\UploadRefused;
use Modules\Files\Services\Browser;
use Modules\Files\Services\FileRepository;
use Modules\Files\Services\FolderAcl;
use Modules\Files\Services\QuotaService;
use Modules\Files\Services\Rights;
use Modules\Files\Services\Spaces;
use Modules\Files\Services\Trash;
use Modules\Files\Services\Uploader;
use RuntimeException;

/**
 * JSON endpoints of the file manager (`backend/files/api/*`). The route filters check the RBAC
 * ceiling (`files.list` plus the operation's permission); every action then checks the folder
 * ACL of the folder or file it touches (`FolderAcl::managerRights()`). A folder or file the user
 * cannot view answers 404, a missing right 403. Paths are normalised (PathNormalizer): traversal,
 * hidden segments and absolute paths answer 400. Every change is written to the activity log;
 * ACL changes to the security log.
 */
final class FileApi extends BackendController
{
    private ?Browser $browser = null;

    public function tree(): ResponseInterface
    {
        $user = $this->currentUser();
        $this->browser()->ensureSpaces($user);

        return $this->json(['tree' => $this->browser()->tree($user)]);
    }

    public function list(): ResponseInterface
    {
        [$disk, $path] = $this->target($this->request->getGet());

        if ($path === null || ! $this->allowed($disk, $path, Rights::VIEW)) {
            return $this->notFound();
        }

        $filters = [];

        foreach (['q', 'type', 'from', 'to', 'tag', 'sort'] as $key) {
            $value = $this->request->getGet($key);

            if (is_string($value)) {
                $filters[$key] = mb_substr($value, 0, 100);
            }
        }

        return $this->json($this->browser()->contents($this->currentUser(), $disk, $path, $filters));
    }

    public function trash(): ResponseInterface
    {
        $user    = $this->currentUser();
        $acl     = $this->browser()->acl();
        $folders = [];

        foreach (Spaces::DISKS as $disk) {
            $folders[$disk] = [];

            foreach (db_connect()->table('files')->distinct()->select('folder')->where('disk', $disk)->where('deleted_at IS NOT NULL')->get()->getResultArray() as $row) {
                if ($acl->managerCan($user, $disk, (string) $row['folder'], Rights::DELETE)) {
                    $folders[$disk][] = (string) $row['folder'];
                }
            }
        }

        $items = [];

        foreach ((new FileRepository())->trashed($folders) as $row) {
            $items[] = $this->browser()->item($row, $acl->managerRights($user, (string) $row['disk'], (string) $row['folder']));
        }

        return $this->json(['files' => $items]);
    }

    public function show(int $id): ResponseInterface
    {
        $row = $this->file($id, Rights::VIEW);

        return $row === null ? $this->notFound() : $this->json($this->browser()->item($row, $this->rightsOf($row)));
    }

    public function download(int $id): DownloadResponse|ResponseInterface
    {
        return $this->send($id, true);
    }

    public function preview(int $id): DownloadResponse|ResponseInterface
    {
        return $this->send($id, false);
    }

    public function thumbnail(int $id): ResponseInterface
    {
        $row = $this->file($id, Rights::VIEW, true);

        if ($row === null || ! is_string($row['thumbnail'] ?? null)) {
            return $this->notFound();
        }

        $disk = $this->storage()->disk('private');

        if (! $disk->exists($row['thumbnail'])) {
            return $this->notFound();
        }

        return $this->response
            ->setHeader('Content-Type', str_ends_with($row['thumbnail'], '.webp') ? 'image/webp' : 'image/jpeg')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, max-age=86400')
            ->setBody($disk->read($row['thumbnail']));
    }

    public function upload(): ResponseInterface
    {
        [$disk, $path] = $this->target($this->request->getPost());

        if ($path === null || ! $this->allowed($disk, $path, Rights::VIEW)) {
            return $this->notFound();
        }

        if (! $this->allowed($disk, $path, Rights::UPLOAD) || Spaces::of($disk, $path) === Spaces::MODULES) {
            return $this->forbidden();
        }

        $this->browser()->acl()->folders()->find($disk, $path) ?? $this->registerPersonal($disk, $path);

        $uploads = $this->request->getFileMultiple('files') ?? [];
        $single  = $this->request->getFile('file');

        if ($single instanceof UploadedFile) {
            $uploads[] = $single;
        }

        if ($uploads === []) {
            return $this->json(['message' => lang('Files.errors.noFiles')], 400);
        }

        $uploader = new Uploader($this->browser()->acl());
        $files    = new FileRepository();
        $rights   = $this->browser()->acl()->managerRights($this->currentUser(), $disk, $path);
        $results  = [];
        $status   = 200;

        foreach ($uploads as $upload) {
            $name = Storage::cleanName($upload->getClientName());

            try {
                $id        = $uploader->upload($this->currentUser(), $disk, $path, $upload);
                $results[] = ['name' => $name, 'ok' => true, 'file' => $this->browser()->item((array) $files->find($id), $rights)];
            } catch (UploadRefused|RuntimeException $e) {
                $results[] = ['name' => $name, 'ok' => false, 'message' => $e->getMessage()];
                $status    = 422;
            }
        }

        return $this->json(['results' => $results, 'message' => $status === 200 ? lang('Files.messages.uploaded', [count($results)]) : lang('Files.errors.someFailed')], $status);
    }

    public function update(int $id): ResponseInterface
    {
        $row = $this->file($id, Rights::EDIT);

        if ($row === null) {
            return $this->missingOrForbidden($id);
        }

        $name = Storage::cleanName(trim((string) $this->request->getPost('name')));
        $ext  = (string) $row['extension'];

        // The extension stays the stored one (the name is only metadata, but it must not lie).
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== $ext) {
            $name .= '.' . $ext;
        }

        $values = [
            'name' => mb_substr($name, 0, 255),
            'alt'  => mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', '', (string) $this->request->getPost('alt'))), 0, 255),
            'tags' => FileRepository::normaliseTags((string) $this->request->getPost('tags')),
        ];
        (new FileRepository())->update($id, $values);
        activity_log('Files', 'file_updated', [
            'resource' => 'files', 'record_id' => (string) $id, 'summary' => $values['name'],
            'before'   => ['name' => $row['name'], 'alt' => $row['alt'], 'tags' => $row['tags']], 'after' => $values,
        ]);

        return $this->json(['message' => lang('Files.messages.saved'), 'file' => $this->browser()->item((array) (new FileRepository())->find($id), $this->rightsOf($row))]);
    }

    public function move(int $id): ResponseInterface
    {
        $row = $this->file($id, Rights::EDIT);

        if ($row === null) {
            return $this->missingOrForbidden($id);
        }

        $to = $this->path((string) $this->request->getPost('to'));

        if ($to === null || $this->browser()->acl()->folders()->find((string) $row['disk'], $to) === null || ! $this->allowed((string) $row['disk'], $to, Rights::VIEW)) {
            return $this->json(['message' => lang('Files.errors.unknownFolder')], 404);
        }

        if (! $this->allowed((string) $row['disk'], $to, Rights::UPLOAD) || Spaces::of((string) $row['disk'], $to) === Spaces::MODULES) {
            return $this->forbidden();
        }

        if ($to !== $row['folder']) {
            try {
                (new QuotaService())->check((string) $row['disk'], $to, (int) $row['size']);
            } catch (RuntimeException $e) {
                return $this->json(['message' => $e->getMessage()], 422);
            }

            (new FileRepository())->move($id, $to);
            activity_log('Files', 'file_moved', ['resource' => 'files', 'record_id' => (string) $id, 'summary' => (string) $row['name'], 'before' => ['folder' => $row['folder']], 'after' => ['folder' => $to]]);
        }

        return $this->json(['message' => lang('Files.messages.moved')]);
    }

    public function delete(int $id): ResponseInterface
    {
        $row = $this->file($id, Rights::DELETE);

        if ($row === null) {
            return $this->missingOrForbidden($id);
        }

        (new Trash())->trash($id, (int) $this->currentUser()->id);
        activity_log('Files', 'file_trashed', ['resource' => 'files', 'record_id' => (string) $id, 'summary' => (string) $row['name']]);

        return $this->json(['message' => lang('Files.messages.trashed')]);
    }

    public function restore(int $id): ResponseInterface
    {
        $row = $this->file($id, Rights::DELETE, true);

        if ($row === null || $row['deleted_at'] === null) {
            return $this->notFound();
        }

        (new Trash())->restore($id);
        activity_log('Files', 'file_restored', ['resource' => 'files', 'record_id' => (string) $id, 'summary' => (string) $row['name']]);

        return $this->json(['message' => lang('Files.messages.restored')]);
    }

    public function purge(int $id): ResponseInterface
    {
        $row = $this->file($id, Rights::DELETE, true);

        if ($row === null || $row['deleted_at'] === null) {
            return $this->notFound();
        }

        (new Trash())->purge($id);
        activity_log('Files', 'file_purged', ['resource' => 'files', 'record_id' => (string) $id, 'summary' => (string) $row['name']]);

        return $this->json(['message' => lang('Files.messages.purged')]);
    }

    public function createFolder(): ResponseInterface
    {
        [$disk, $parent] = $this->target($this->request->getPost());
        $name            = trim((string) $this->request->getPost('name'));

        if ($parent === null || ! $this->allowed($disk, $parent, Rights::VIEW)) {
            return $this->notFound();
        }

        if (! $this->canManageFolder($disk, $parent) || Spaces::of($disk, $parent) === Spaces::MODULES) {
            return $this->forbidden();
        }

        if (($error = self::nameError($name)) !== null) {
            return $this->json(['message' => $error], 422);
        }

        $path    = $parent . '/' . $name;
        $folders = $this->browser()->acl()->folders();

        if (strlen($path) > PathNormalizer::MAX_LENGTH - 40 || $folders->find($disk, $path) !== null) {
            return $this->json(['message' => lang('Files.errors.folderExists')], 422);
        }

        $folders->find($disk, $parent) ?? $this->registerPersonal($disk, $parent);
        $folders->ensure($disk, $path, ['created_by' => (int) $this->currentUser()->id]);
        activity_log('Files', 'folder_created', ['resource' => 'files', 'summary' => $disk . ':' . $path]);

        return $this->json(['message' => lang('Files.messages.folderCreated'), 'path' => $path], 201);
    }

    public function renameFolder(): ResponseInterface
    {
        [$disk, $path] = $this->target($this->request->getPost());
        $name          = trim((string) $this->request->getPost('name'));
        $folders       = $this->browser()->acl()->folders();

        if ($path === null || $folders->find($disk, $path) === null || ! $this->allowed($disk, $path, Rights::VIEW)) {
            return $this->notFound();
        }

        if (Spaces::isRoot($disk, $path) || ! $this->canManageFolder($disk, $path) || ! $this->canManageFolder($disk, Spaces::parent($path))) {
            return $this->forbidden();
        }

        if (($error = self::nameError($name)) !== null) {
            return $this->json(['message' => $error], 422);
        }

        $to = Spaces::parent($path) . '/' . $name;

        if ($to !== $path) {
            if ($folders->find($disk, $to) !== null) {
                return $this->json(['message' => lang('Files.errors.folderExists')], 422);
            }

            $folders->rename($disk, $path, $to);
            activity_log('Files', 'folder_renamed', ['resource' => 'files', 'summary' => $disk . ':' . $to, 'before' => ['path' => $path], 'after' => ['path' => $to]]);
        }

        return $this->json(['message' => lang('Files.messages.folderRenamed'), 'path' => $to]);
    }

    public function deleteFolder(): ResponseInterface
    {
        [$disk, $path] = $this->target($this->request->getPost());
        $folders       = $this->browser()->acl()->folders();
        $folder        = $path === null ? null : $folders->find($disk, $path);

        if ($folder === null || ! $this->allowed($disk, $path, Rights::VIEW)) {
            return $this->notFound();
        }

        if (Spaces::isRoot($disk, $path) || ! $this->canManageFolder($disk, $path) || ! $this->allowed($disk, $path, Rights::DELETE)) {
            return $this->forbidden();
        }

        if (! $folders->isEmpty($disk, $path)) {
            return $this->json(['message' => lang('Files.errors.folderNotEmpty')], 422);
        }

        $folders->delete($folder['id']);
        activity_log('Files', 'folder_deleted', ['resource' => 'files', 'summary' => $disk . ':' . $path]);

        return $this->json(['message' => lang('Files.messages.folderDeleted')]);
    }

    public function acl(): ResponseInterface
    {
        [$disk, $path] = $this->target($this->request->getGet());
        $acl           = $this->browser()->acl();

        if ($path === null || ! $this->allowed($disk, $path, Rights::VIEW)) {
            return $this->notFound();
        }

        if (! $this->allowed($disk, $path, Rights::MANAGE) || Spaces::of($disk, $path) === Spaces::MODULES) {
            return $this->forbidden();
        }

        $folder = $acl->folders()->find($disk, $path);
        $source = $acl->source($disk, $path);

        return $this->json([
            'disk'      => $disk,
            'path'      => $path,
            'own'       => $folder !== null && $folder['own_permissions'],
            'inherited' => $source === null ? null : ['path' => $source['path'], 'entries' => $acl->folders()->permissions($source['id'])],
            'entries'   => $folder !== null && $folder['own_permissions'] ? $acl->folders()->permissions($folder['id']) : [],
            'quota'     => $folder['quota'] ?? null,
            'canQuota'  => Authorization::current()->can('files.all'),
            'groups'    => array_map(static fn (array $g): string => $g['title'] !== '' ? $g['title'] : $g['name'], (new GroupRegistry())->all()),
            'rights'    => Rights::ALL,
        ]);
    }

    public function saveAcl(): ResponseInterface
    {
        $data          = $this->request->getJSON(true);
        $data          = is_array($data) ? $data : $this->request->getPost();
        [$disk, $path] = $this->target($data);
        $acl           = $this->browser()->acl();

        if ($path === null || ! $this->allowed($disk, $path, Rights::VIEW)) {
            return $this->notFound();
        }

        if (! $this->allowed($disk, $path, Rights::MANAGE) || Spaces::of($disk, $path) === Spaces::MODULES) {
            return $this->forbidden();
        }

        $own     = ! empty($data['own']);
        $entries = [];
        $groups  = (new GroupRegistry())->all();

        foreach (is_array($data['entries'] ?? null) ? $data['entries'] : [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $type    = (string) ($entry['subject_type'] ?? '');
            $subject = trim((string) ($entry['subject_id'] ?? ''));
            $rights  = Rights::parse(is_array($entry['rights'] ?? null) ? $entry['rights'] : (string) ($entry['rights'] ?? ''));

            if ($type === 'authenticated') {
                $subject = '';
            }

            $valid = match ($type) {
                'authenticated' => true,
                'group'         => isset($groups[$subject]),
                'user'          => ctype_digit($subject) && db_connect()->table('users')->where('id', (int) $subject)->countAllResults() === 1,
                default         => false,
            };

            if (! $valid) {
                return $this->json(['message' => lang('Files.errors.unknownSubject')], 422);
            }

            if ($rights !== []) {
                $entries[$type . ':' . $subject] = ['subject_type' => $type, 'subject_id' => $subject, 'rights' => $rights];
            }
        }

        $quota = null;

        if (Authorization::current()->can('files.all') && isset($data['quota']) && $data['quota'] !== '') {
            if (! is_numeric($data['quota']) || (int) $data['quota'] < 0 || (int) $data['quota'] > 1048576) {
                return $this->json(['message' => lang('Files.errors.quotaValue')], 422);
            }

            $quota = (int) $data['quota'] === 0 ? null : (int) $data['quota'] * 1024 * 1024;
        }

        $folders = $acl->folders();
        $folder  = $folders->find($disk, $path) ?? $this->registerPersonal($disk, $path);
        $folder ??= $folders->ensure($disk, $path);
        $before  = ['own' => $folder['own_permissions'], 'entries' => $folders->permissions($folder['id']), 'quota' => $folder['quota']];
        $folders->savePermissions($folder['id'], $own, array_values($entries));

        if (Authorization::current()->can('files.all') && array_key_exists('quota', $data)) {
            $folders->setQuota($folder['id'], $quota);
        }

        security_log('Files', 'acl_changed', [
            'resource' => 'files', 'record_id' => (string) $folder['id'], 'summary' => $disk . ':' . $path,
            'before'   => $before, 'after' => ['own' => $own, 'entries' => array_values($entries), 'quota' => $quota],
        ]);

        return $this->json(['message' => lang('Files.messages.aclSaved')]);
    }

    /**
     * The disk and normalised path of a request (null path: invalid).
     *
     * @param array<array-key, mixed>|object|null $input
     *
     * @return array{0: string, 1: string|null}
     */
    private function target(array|object|null $input): array
    {
        $input = is_array($input) ? $input : [];
        $disk  = (string) ($input['disk'] ?? '');

        if (! in_array($disk, Spaces::DISKS, true)) {
            return ['public', null];
        }

        return [$disk, $this->path((string) ($input['path'] ?? ''))];
    }

    private function path(string $path): ?string
    {
        try {
            $path = PathNormalizer::normalize($path);
        } catch (StorageException) {
            return null;
        }

        return Spaces::of('private', $path) === null && Spaces::of('public', $path) === null ? null : $path;
    }

    private function allowed(string $disk, string $path, string $right): bool
    {
        return $this->browser()->acl()->managerCan($this->currentUser(), $disk, $path, $right);
    }

    /**
     * Creating, renaming and deleting folders: `files.folders` (route) and `manage` in the folder,
     * or `upload` inside the user's own personal space.
     */
    private function canManageFolder(string $disk, string $path): bool
    {
        if ($path === '') {
            return false;
        }

        $user = $this->currentUser();

        if (Spaces::personalOwner($disk, $path) === (int) $user->id) {
            return $this->allowed($disk, $path, Rights::UPLOAD);
        }

        return $this->allowed($disk, $path, Rights::MANAGE);
    }

    /**
     * The personal root of the current user, registered on demand.
     *
     * @return array{id: int, disk: string, path: string, owner_id: int|null, quota: int|null, own_permissions: bool}|null
     */
    private function registerPersonal(string $disk, string $path): ?array
    {
        $owner = Spaces::personalOwner($disk, $path);

        if ($owner === null || $owner !== (int) $this->currentUser()->id || ! Spaces::personalEnabled($this->currentUser())) {
            return null;
        }

        return $this->browser()->acl()->folders()->ensure('private', Spaces::personalPath($owner), ['owner_id' => $owner, 'own_permissions' => true]);
    }

    /**
     * A file the user holds a right on (null: unknown, not viewable or without the right).
     *
     * @return array<string, mixed>|null
     */
    private function file(int $id, string $right, bool $trashed = false): ?array
    {
        $row = (new FileRepository())->find($id);

        if ($row === null || (! $trashed && $row['deleted_at'] !== null)) {
            return null;
        }

        $rights = $this->rightsOf($row);

        if ($row['deleted_at'] !== null && ! in_array(Rights::DELETE, $rights, true)) {
            return null;
        }

        return in_array($right, $rights, true) ? $row : null;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<string>
     */
    private function rightsOf(array $row): array
    {
        return $this->browser()->acl()->managerRights($this->currentUser(), (string) $row['disk'], (string) $row['folder']);
    }

    private function missingOrForbidden(int $id): ResponseInterface
    {
        $row = (new FileRepository())->find($id);

        return $row !== null && $row['deleted_at'] === null && in_array(Rights::VIEW, $this->rightsOf($row), true) ? $this->forbidden() : $this->notFound();
    }

    private function send(int $id, bool $download): DownloadResponse|ResponseInterface
    {
        $row = $this->file($id, Rights::VIEW, true);

        if ($row === null) {
            return $this->notFound();
        }

        $stored = $this->storage()->findById((int) $row['stored_file_id']);
        $disk   = $stored === null ? null : $this->storage()->disk($stored->disk);

        if ($stored === null || $disk === null || ! $disk->exists($stored->path)) {
            return $this->notFound();
        }

        FolderAcl::logForeignRead($this->currentUser(), (string) $row['disk'], (string) $row['folder'], (string) $row['name'], $download ? 'download' : 'preview');

        $text     = in_array($stored->extension, ['txt', 'csv', 'md'], true);
        $response = new DownloadResponse((string) $row['name'], false);
        $response->setFilePath($disk->localPath($stored->path));
        $response->setContentType($text ? 'text/plain' : $stored->mime, str_starts_with($stored->mime, 'text/') || $text ? 'UTF-8' : '');
        $response->noCache();

        foreach (PrivateFiles::headers($stored) as $name => $value) {
            $response->setHeader($name, $value);
        }

        if (! $download && ($stored->isInline() || $text)) {
            $response->inline();
        }

        $response->buildHeaders();

        return $response;
    }

    private static function nameError(string $name): ?string
    {
        if ($name === '' || mb_strlen($name) > 80 || str_starts_with($name, '.') || preg_match('/^[\p{L}\p{N} _().,&+\'-]+$/u', $name) !== 1 || trim($name) !== $name) {
            return lang('Files.errors.folderName');
        }

        return null;
    }

    private function notFound(): ResponseInterface
    {
        return $this->json(['message' => lang('Files.errors.notFound')], 404);
    }

    private function forbidden(): ResponseInterface
    {
        return $this->json(['message' => lang('Files.errors.forbidden')], 403);
    }

    private function browser(): Browser
    {
        return $this->browser ??= new Browser();
    }

    private function storage(): Storage
    {
        return service('storage');
    }
}
