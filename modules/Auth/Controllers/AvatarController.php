<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\DownloadResponse;
use Modules\Auth\Services\Avatars;
use Modules\Core\Controllers\PrivateFiles;
use Modules\Core\Storage\Storage;

/**
 * `GET files/avatar/{key}`: a user's avatar (private disk). Only for signed-in users, or with a
 * valid signed URL (`?expires=…&signature=…`, API clients); only keys that are someone's current
 * avatar (no other private file can be read here). Anything else is a 404. The response is a
 * private, immutable cache entry for a year: the URL changes with each new avatar.
 */
final class AvatarController extends Controller
{
    public const CACHE = 'private, max-age=31536000, immutable';

    public function show(string $key): DownloadResponse
    {
        $signed = Avatars::validSignature($key, (string) $this->request->getGet('expires'), (string) $this->request->getGet('signature'));

        if ((! auth()->loggedIn() && ! $signed) || ! Avatars::isAvatar($key)) {
            throw PageNotFoundException::forPageNotFound();
        }

        /** @var Storage $storage */
        $storage = service('storage');
        $file    = $storage->find($key);

        if ($file === null || ! $file->isImage() || $storage->disk($file->disk)->isPublic() || ! $storage->disk($file->disk)->exists($file->path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $response = new DownloadResponse($file->originalName, false);
        $response->setFilePath($storage->disk($file->disk)->localPath($file->path));
        $response->setContentType($file->mime);

        foreach (PrivateFiles::headers($file) as $name => $value) {
            $response->setHeader($name, $value);
        }

        $response->removeHeader('Cache-Control');
        $response->setHeader('Cache-Control', self::CACHE);
        $response->inline();
        $response->buildHeaders();

        return $response;
    }
}
