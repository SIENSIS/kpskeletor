<?php

declare(strict_types=1);

namespace Modules\Core\Storage;

use League\Flysystem\Config as FlysystemConfig;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;
use League\Flysystem\Visibility;

/**
 * One storage disk (`public` or `private`) over Flysystem. Every path is relative to the disk
 * root and normalised by PathNormalizer (no traversal, no hidden files). Folders and files are
 * created with the disk's permissions (private: `0750`/`0640`).
 *
 * Modules use these methods, never filesystem paths, so another adapter can replace the local
 * one later.
 */
final class Disk
{
    private readonly Filesystem $filesystem;

    /**
     * @param array{root: string, url: string|null, dirPermission: int, filePermission: int} $config
     */
    public function __construct(private readonly string $name, private readonly array $config)
    {
        $visibility = PortableVisibilityConverter::fromArray([
            'file' => ['public' => $config['filePermission'], 'private' => $config['filePermission']],
            'dir'  => ['public' => $config['dirPermission'], 'private' => $config['dirPermission']],
        ], Visibility::PRIVATE);

        $adapter = new LocalFilesystemAdapter(
            $config['root'],
            $visibility,
            LOCK_EX,
            LocalFilesystemAdapter::DISALLOW_LINKS,
            null,
            true, // lazy root creation: the folder is created on the first write
        );

        $this->filesystem = new Filesystem($adapter, [
            FlysystemConfig::OPTION_VISIBILITY           => Visibility::PRIVATE,
            FlysystemConfig::OPTION_DIRECTORY_VISIBILITY => Visibility::PRIVATE,
        ]);
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * Whether the web server serves the disk directly (it has a public URL).
     */
    public function isPublic(): bool
    {
        return $this->config['url'] !== null;
    }

    public function write(string $path, string $contents): void
    {
        $this->filesystem->write(PathNormalizer::normalize($path), $contents);
    }

    /**
     * @param resource $stream
     */
    public function writeStream(string $path, $stream): void
    {
        $this->filesystem->writeStream(PathNormalizer::normalize($path), $stream);
    }

    public function read(string $path): string
    {
        return $this->filesystem->read(PathNormalizer::normalize($path));
    }

    /**
     * @return resource
     */
    public function readStream(string $path)
    {
        return $this->filesystem->readStream(PathNormalizer::normalize($path));
    }

    public function exists(string $path): bool
    {
        return $this->filesystem->fileExists(PathNormalizer::normalize($path));
    }

    public function directoryExists(string $path): bool
    {
        return $this->filesystem->directoryExists(PathNormalizer::normalize($path));
    }

    public function delete(string $path): void
    {
        $this->filesystem->delete(PathNormalizer::normalize($path));
    }

    public function createDirectory(string $path): void
    {
        $this->filesystem->createDirectory(PathNormalizer::normalize($path));
    }

    public function deleteDirectory(string $path): void
    {
        $this->filesystem->deleteDirectory(PathNormalizer::normalize($path));
    }

    public function move(string $from, string $to): void
    {
        $this->filesystem->move(PathNormalizer::normalize($from), PathNormalizer::normalize($to));
    }

    public function copy(string $from, string $to): void
    {
        $this->filesystem->copy(PathNormalizer::normalize($from), PathNormalizer::normalize($to));
    }

    public function size(string $path): int
    {
        return $this->filesystem->fileSize(PathNormalizer::normalize($path));
    }

    public function lastModified(string $path): int
    {
        return $this->filesystem->lastModified(PathNormalizer::normalize($path));
    }

    /**
     * Paths of the files (and folders) inside a folder; `''` is the disk root.
     *
     * @return list<array{path: string, type: string}>
     */
    public function list(string $folder = '', bool $deep = false): array
    {
        $items = [];

        foreach ($this->filesystem->listContents(PathNormalizer::normalize($folder, true), $deep) as $item) {
            /** @var StorageAttributes $item */
            $items[] = ['path' => $item->path(), 'type' => $item->type()];
        }

        return $items;
    }

    /**
     * Public URL of a file of a public disk.
     *
     * @throws StorageException on a private disk
     */
    public function url(string $path): string
    {
        if ($this->config['url'] === null) {
            throw StorageException::notPublic($this->name);
        }

        $path = PathNormalizer::normalize($path);

        return base_url($this->config['url'] . '/' . implode('/', array_map(rawurlencode(...), explode('/', $path))));
    }

    /**
     * Absolute local path of a file, for the Core's private file controller only (it streams the
     * file). Modules never use it.
     *
     * @internal
     */
    public function localPath(string $path): string
    {
        return rtrim($this->config['root'], '/\\') . DIRECTORY_SEPARATOR . PathNormalizer::normalize($path);
    }
}
