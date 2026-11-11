<?php

declare(strict_types=1);

namespace Modules\Files\Tests;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Files\File;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\TestResponse;
use Modules\Auth\Tests\AuthTestCase;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Registry\DeclarationRegistry;
use Modules\Core\Tests\StorageFixturesTrait;
use Modules\Files\Policies\PolicyRegistry;
use Modules\Files\Services\FolderRepository;
use Modules\Files\Services\Uploader;

/**
 * Base of the Files tests: the Auth fixture (every module migrated, catalogue and grants synced,
 * requests with session and CSRF), the declarations of every module (settings defaults), Storage
 * disks in a temporary folder, and helpers that register folders with their ACL, upload files
 * and call the JSON endpoints.
 */
abstract class FilesTestCase extends AuthTestCase
{
    use StorageFixturesTrait;

    protected function setUp(): void
    {
        parent::setUp();
        service('routes')->loadRoutes();
        $manifests = [];

        foreach ((new ModuleDiscovery())->discover() as $name => $module) {
            if ($module->isValid() && $module->manifest !== null) {
                $manifests[$name] = $module->manifest;
            }
        }

        $registry = new DeclarationRegistry();
        $registry->store($registry->build($manifests));
        (new CacheInvalidator())->invalidate(['menus', 'modules']);
        PolicyRegistry::reset();
        $this->makeStorage();
    }

    protected function tearDown(): void
    {
        Events::removeAllListeners(PolicyRegistry::EVENT);
        PolicyRegistry::reset();
        $this->removeStorageRoot();
        parent::tearDown();
    }

    /**
     * Registers a folder. `$entries` null: it inherits; otherwise its own ACL
     * (list of [subject type, subject id, rights]).
     *
     * @param list<array{0: string, 1: string, 2: string}>|null $entries
     */
    protected function folder(string $disk, string $path, ?array $entries = null, ?int $quota = null): int
    {
        $now = Time::now()->toDateTimeString();
        db_connect()->table('folders')->insert([
            'disk' => $disk, 'path' => $path, 'quota' => $quota, 'own_permissions' => $entries === null ? 0 : 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int) db_connect()->insertID();

        foreach ($entries ?? [] as [$type, $subject, $rights]) {
            db_connect()->table('folder_permissions')->insert(['folder_id' => $id, 'subject_type' => $type, 'subject_id' => $subject, 'rights' => $rights]);
        }

        return $id;
    }

    protected function folders(): FolderRepository
    {
        return new FolderRepository();
    }

    /**
     * Uploads contents as a user through the file manager's rules; returns the `files` id.
     */
    protected function upload(User $user, string $disk, string $folder, string $contents, string $name): int
    {
        $path = $this->tempFile($contents);

        try {
            return (new Uploader())->upload($user, $disk, $folder, new File($path), $name);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    protected function apiJson(string $method, string $path, array $params = [], int $status = 200): array
    {
        $response = $this->send($method, 'backend/files/api/' . $path, $params);
        $this->assertSame($status, $response->response()->getStatusCode(), $method . ' ' . $path . ': ' . $response->response()->getBody());
        $data = json_decode((string) $response->response()->getBody(), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function statusOf(string $method, string $path, array $params = []): int
    {
        try {
            return $this->send($method, $path, $params)->response()->getStatusCode();
        } catch (PageNotFoundException) {
            return 404;
        }
    }

    protected function getFile(string $path): TestResponse
    {
        return $this->send('GET', $path);
    }

    protected function setSetting(string $key, mixed $value): void
    {
        service('settings')->set($key, $value);
    }
}
