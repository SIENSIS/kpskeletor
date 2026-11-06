<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Modules\Core\Storage\Storage;
use Modules\Core\Storage\StoredFile;

/**
 * GET files/private/{key}: only the user the access rule allows (until session 22: the uploader
 * and superadmins) gets the file, with its stored MIME type, nosniff, a sandboxing CSP, no
 * caching and `attachment` unless it is a raster image or a PDF; everybody else, unknown keys,
 * public-disk files and files missing on disk get a 404.
 *
 * @internal
 */
final class PrivateFilesTest extends RbacTestCase
{
    use FeatureTestTrait;
    use StorageFixturesTrait;

    private Storage $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = $this->makeStorage();
    }

    protected function tearDown(): void
    {
        $this->removeStorageRoot();
        parent::tearDown();
    }

    /**
     * Stores a file uploaded by a new user (the owner).
     */
    private function storeAs(string $contents, string $name, string $disk = 'private'): StoredFile
    {
        $user = $this->createUser('owner' . bin2hex(random_bytes(3)) . '@example.com');
        $this->actAs($user);
        $file = $this->storage->storeContents($contents, $name, $disk, 'users/' . $user->id);
        $this->actAs(null);

        return $file;
    }

    private function assertNotFound(?int $userId, string $path): void
    {
        try {
            $this->getAs($userId, $path)->assertStatus(404);
        } catch (PageNotFoundException) {
            $this->addToAssertionCount(1); // rendered as the theme's 404 page outside tests
        }
    }

    private function getAs(?int $userId, string $path): TestResponse
    {
        $this->actAs($userId === null ? null : model(UserModel::class)->findById($userId));
        $session = $_SESSION;

        return $this->withSession($session)->get($path);
    }

    public function testTheOwnerSeesAnImageInline(): void
    {
        $file   = $this->storeAs(self::png(), 'photo.png');
        $result = $this->getAs($file->uploadedBy, 'files/private/' . $file->key);

        $result->assertStatus(200);
        $this->assertSame('image/png', $result->response()->getHeaderLine('Content-Type'));
        $this->assertSame('nosniff', $result->response()->getHeaderLine('X-Content-Type-Options'));
        $this->assertStringStartsWith('inline; filename="photo.png"', $result->response()->getHeaderLine('Content-Disposition'));
        $this->assertStringContainsString('no-store', $result->response()->getHeaderLine('Cache-Control'));
        $this->assertStringContainsString('private', $result->response()->getHeaderLine('Cache-Control'));
        $csp = $result->response()->getHeaderLine('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString('sandbox', $csp);
        $this->assertSame('SAMEORIGIN', $result->response()->getHeaderLine('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $result->response()->getHeaderLine('Referrer-Policy'), 'The global security headers are added too.');
        $this->assertSame((string) $file->size, $result->response()->getHeaderLine('Content-Length'));
    }

    public function testDownloadForcesAttachment(): void
    {
        $file   = $this->storeAs(self::png(), 'photo.png');
        $result = $this->getAs($file->uploadedBy, 'files/private/' . $file->key . '?download=1');

        $result->assertStatus(200);
        $this->assertStringStartsWith('attachment; filename="photo.png"', $result->response()->getHeaderLine('Content-Disposition'));
    }

    public function testDocumentsThatAreNotImagesOrPdfAreAttachments(): void
    {
        $file   = $this->storeAs("name,age\nAna,30\n", 'people.csv');
        $result = $this->getAs($file->uploadedBy, 'files/private/' . $file->key);

        $result->assertStatus(200);
        $this->assertSame('text/csv; charset=UTF-8', $result->response()->getHeaderLine('Content-Type'));
        $this->assertStringStartsWith('attachment;', $result->response()->getHeaderLine('Content-Disposition'));
        $this->assertSame('nosniff', $result->response()->getHeaderLine('X-Content-Type-Options'));
    }

    public function testPdfIsInlineWithoutSandbox(): void
    {
        $file   = $this->storeAs(self::pdf(), 'Informe anual.pdf');
        $result = $this->getAs($file->uploadedBy, 'files/private/' . $file->key);

        $result->assertStatus(200);
        $this->assertSame('application/pdf', $result->response()->getHeaderLine('Content-Type'));
        $this->assertStringContainsString("filename*=UTF-8''Informe%20anual.pdf", $result->response()->getHeaderLine('Content-Disposition'));
        $this->assertStringStartsWith('inline;', $result->response()->getHeaderLine('Content-Disposition'));
        $csp = $result->response()->getHeaderLine('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringNotContainsString('sandbox', $csp);
    }

    public function testOtherUsersAndGuestsGetNotFound(): void
    {
        $file  = $this->storeAs(self::png(), 'photo.png');
        $other = $this->createUser('other@example.com', 'admin');

        $this->assertNotFound((int) $other->id, 'files/private/' . $file->key);
        $this->assertNotFound(null, 'files/private/' . $file->key);
    }

    public function testSuperadminsCanReadEveryPrivateFile(): void
    {
        $file = $this->storeAs(self::png(), 'photo.png');
        $root = $this->createUser('root@example.com', 'superadmin');

        $this->getAs((int) $root->id, 'files/private/' . $file->key)->assertStatus(200);
    }

    public function testUnknownMalformedPublicAndMissingFilesAreNotFound(): void
    {
        $file   = $this->storeAs(self::png(), 'photo.png');
        $public = $this->storeAs(self::png(), 'public.png', 'public');
        $owner  = $file->uploadedBy;

        $this->assertNotFound($owner, 'files/private/' . str_repeat('a', 32));
        $this->assertNotFound($owner, 'files/private/..%2F..%2F.env');
        $this->assertNotFound($owner, 'files/private/' . strtoupper($file->key));
        $this->assertNotFound($public->uploadedBy, 'files/private/' . $public->key);

        $this->storage->disk('private')->delete($file->path);
        $this->assertNotFound($owner, 'files/private/' . $file->key);
    }

    public function testAMisconfiguredAccessRuleDeniesEverything(): void
    {
        $file                                = $this->storeAs(self::png(), 'photo.png');
        $this->storage->config()->accessRule = \stdClass::class;

        $this->assertNotFound($file->uploadedBy, 'files/private/' . $file->key);
    }

    public function testPrivateFilesAreNotUnderThePublicFolder(): void
    {
        $config = config(\Config\Storage::class);

        $this->assertStringStartsWith(realpath(WRITEPATH) ?: WRITEPATH, $config->disks['private']['root']);
        $this->assertStringStartsNotWith(realpath(FCPATH) ?: FCPATH, $config->disks['private']['root']);
        $this->assertSame(0750, $config->disks['private']['dirPermission']);
        $this->assertSame(0640, $config->disks['private']['filePermission']);
        $this->assertNull($config->disks['private']['url']);
    }
}
