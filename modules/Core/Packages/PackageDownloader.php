<?php

declare(strict_types=1);

namespace Modules\Core\Packages;

use Modules\Core\Http\OutboundClient;
use Modules\Core\Http\OutboundException;

/**
 * Downloads a package for `modules:install <url>` / `themes:install <url>` through
 * OutboundClient (HTTPS, allowed hosts only, public addresses, size limit of `Config\Outbound`)
 * into a private file of `writable/packages/`, with its signature `<url>.minisig` when the
 * server has one.
 */
final class PackageDownloader
{
    public function __construct(private readonly ?OutboundClient $client = null, private readonly ?string $folder = null)
    {
    }

    /**
     * @return array{file: string, signature: string|null} The caller deletes the file
     *
     * @throws PackageRefused
     */
    public function download(string $url): array
    {
        $client = $this->client ?? new OutboundClient();

        try {
            $response = $client->get($url, ['Accept' => 'application/zip, application/octet-stream']);
        } catch (OutboundException $e) {
            throw new PackageRefused(lang('Core.packages.downloadFailed', [$e->getMessage()]), 0, $e);
        }

        if (! $response->ok()) {
            throw new PackageRefused(lang('Core.packages.downloadFailed', ['HTTP ' . $response->status]));
        }

        $signature = null;

        try {
            $signed = $client->get($url . '.minisig');

            if ($signed->ok() && strlen($signed->body) < 4096) {
                $signature = $signed->body;
            }
        } catch (OutboundException) {
            // No signature published: PackageValidator decides whether one is required.
        }

        $folder = rtrim($this->folder ?? WRITEPATH . 'packages', '/');

        if (! is_dir($folder)) {
            @mkdir($folder, 0750, true);
        }

        $file = $folder . '/download-' . bin2hex(random_bytes(8)) . '.zip';

        if (file_put_contents($file, $response->body) === false) {
            throw new PackageRefused(lang('Core.packages.notWritable', [$folder]));
        }

        @chmod($file, 0640);

        return ['file' => $file, 'signature' => $signature];
    }
}
