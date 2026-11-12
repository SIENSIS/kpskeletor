<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Storage\PathNormalizer;
use Modules\Core\Storage\Picker\PickerField;
use Modules\Core\Storage\Picker\UploadToken;
use Modules\Core\Storage\StorageException;
use Modules\Core\Storage\UploadRefused;
use RuntimeException;

/**
 * The endpoints of the file picker (`core/forms/file-picker`, docs/files.md "File picker"):
 *
 * - `POST files/picker/upload` (`token`, `file`; `ratelimit:uploads`): stores one file into the
 *   token's folder through the configured `PickerBackend` and returns it as JSON;
 * - `GET files/picker/browse?token=…&path=…`: the folders and files the user may choose from.
 *
 * **The field grants the upload, not the file manager:** no `files.*` permission is needed, only a
 * signed token issued to the signed-in user when the field was rendered. A missing, tampered,
 * expired or someone else's token is refused (403, `security` entry `upload_token_refused`).
 */
final class FilePicker extends Controller
{
    public function upload(): ResponseInterface
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return $this->error(401, lang('Core.picker.errors.login'));
        }

        $token = $this->token($user, (string) $this->request->getPost('token'), 'upload');

        if ($token === null) {
            return $this->error(403, lang('Core.picker.errors.token'));
        }

        $file = $this->request->getFile('file');

        if (! $file instanceof UploadedFile) {
            return $this->error(422, lang('Core.storage.refused.invalid'));
        }

        try {
            $stored = PickerField::backend()->upload($user, $token, $file);
        } catch (UploadRefused $refused) {
            return $this->error(422, $refused->getMessage());
        } catch (StorageException) {
            return $this->error(422, lang('Core.storage.refused.invalid'));
        } catch (RuntimeException $e) {
            return $this->error(422, $e->getMessage());
        }

        return $this->response->setStatusCode(201)->setJSON(['file' => PickerField::describe($stored)]);
    }

    public function browse(): ResponseInterface
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return $this->error(401, lang('Core.picker.errors.login'));
        }

        $token = $this->token($user, (string) $this->request->getGet('token'), 'browse');

        if ($token === null) {
            return $this->error(403, lang('Core.picker.errors.token'));
        }

        $path = trim((string) ($this->request->getGet('path') ?? ''));

        try {
            $path = $path === '' ? $token->folder : PathNormalizer::normalize($path);
        } catch (StorageException) {
            return $this->error(404, lang('Core.picker.errors.folder'));
        }

        $listing = $token->inRoots($path) ? PickerField::backend()->browse($user, $token, $path) : null;

        if ($listing === null) {
            return $this->error(404, lang('Core.picker.errors.folder'));
        }

        $files = [];

        foreach ($listing['files'] as $file) {
            if ($token->types === [] || in_array($file->extension, $token->types, true)) {
                $files[] = PickerField::describe($file);
            }
        }

        $roots = [];

        foreach (array_values(array_unique([$token->folder, ...$token->roots])) as $root) {
            $roots[] = ['path' => $root, 'name' => $root];
        }

        $parent = null;

        if (! in_array($path, [$token->folder, ...$token->roots], true)) {
            $slash  = strrpos($path, '/');
            $parent = $slash === false ? null : substr($path, 0, $slash);
        }

        return $this->response->setJSON([
            'path'    => $path,
            'parent'  => $parent,
            'roots'   => $roots,
            'folders' => $listing['folders'],
            'files'   => $files,
        ]);
    }

    /**
     * The request's token when it is valid, belongs to this user and allows the action.
     */
    private function token(User $user, string $value, string $action): ?UploadToken
    {
        $token = UploadToken::decode($value);
        $valid = $token !== null && $token->userId === (int) $user->id
            && ($action === 'upload' ? $token->canUpload() : $token->canBrowse());

        if ($valid) {
            return $token;
        }

        security_log('Core', 'upload_token_refused', [
            'summary' => $action . ($token === null ? ': invalid or expired token' : ': token of another user or mode'),
        ]);

        return null;
    }

    private function error(int $status, string $message): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON(['message' => $message]);
    }
}
