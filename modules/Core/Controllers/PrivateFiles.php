<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\DownloadResponse;
use Modules\Core\Storage\Storage;
use Modules\Core\Storage\StoredFile;

/**
 * GET files/private/{key}: the only way to read a file of the `private` disk. The file's access
 * rule (Config\Storage::$accessRule) decides; anything not allowed, unknown or missing is a 404
 * (the existence of a file is not revealed). The response carries the stored MIME type,
 * `X-Content-Type-Options: nosniff`, `Content-Disposition: attachment` for everything that is not
 * a raster image or a PDF (always with `?download=1`), a sandboxing Content-Security-Policy and
 * `Cache-Control: private, no-store`.
 */
final class PrivateFiles extends Controller
{
    public function show(string $key): DownloadResponse
    {
        /** @var Storage $storage */
        $storage = service('storage');
        $file    = $storage->find($key);

        if ($file === null || $storage->disk($file->disk)->isPublic() || ! $storage->canView(auth()->user(), $file)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $disk = $storage->disk($file->disk);

        if (! $disk->exists($file->path)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $response = new DownloadResponse($file->originalName, false);
        $response->setFilePath($disk->localPath($file->path));
        $response->setContentType($file->mime, str_starts_with($file->mime, 'text/') ? 'UTF-8' : '');
        $response->noCache();

        foreach (self::headers($file) as $name => $value) {
            $response->setHeader($name, $value);
        }

        if ($file->isInline() && $this->request->getGet('download') === null) {
            $response->inline();
        }

        $response->buildHeaders(); // Content-Disposition (attachment unless inline) and length

        return $response;
    }

    /**
     * Security headers of a served file (kept by the global `securityheaders` filter, which only
     * adds headers a response does not have).
     *
     * @return array<string, string>
     */
    public static function headers(StoredFile $file): array
    {
        // PDF viewers do not work in a sandboxed document; PDFs never run the site's scripts.
        $csp = "default-src 'none'; img-src 'self'; style-src 'self'; frame-ancestors 'self'"
            . ($file->mime === 'application/pdf' ? '' : '; sandbox');

        return [
            'X-Content-Type-Options'       => 'nosniff',
            'Content-Security-Policy'      => $csp,
            'X-Frame-Options'              => 'SAMEORIGIN',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];
    }
}
