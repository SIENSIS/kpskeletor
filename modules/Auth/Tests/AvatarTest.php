<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use Modules\Auth\Controllers\AvatarController;
use Modules\Auth\Services\Avatars;
use Modules\Core\Storage\Picker\PickerField;
use Modules\Core\Storage\StoredFile;
use Modules\Core\Tests\StorageFixturesTrait;
use Modules\Core\Tests\TestUploadedFile;

/**
 * Avatars: uploaded through the profile's file picker (no files.* permission), saved only when the
 * file came through the field, served at `files/avatar/{key}` to signed-in users (or with a
 * signed URL) with long cache headers; guests get a 404.
 *
 * @internal
 */
final class AvatarTest extends AuthTestCase
{
    use StorageFixturesTrait;

    protected function setUp(): void
    {
        parent::setUp();
        service('routes')->loadRoutes();
        $this->makeStorage();
    }

    protected function tearDown(): void
    {
        $this->removeStorageRoot();
        parent::tearDown();
    }

    private function uploadAvatar(User $user): StoredFile
    {
        $signedIn = auth()->loggedIn();

        if (! $signedIn) {
            auth()->login($user);
        }

        try {
            return PickerField::backend()->upload($user, Avatars::field()->scope($user), TestUploadedFile::fromContents(self::png(16, 16), 'me.png'));
        } finally {
            if (! $signedIn) {
                auth()->logout();
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function profile(string $avatar): array
    {
        return ['name' => 'Ana', 'email' => 'ana@example.com', 'locale' => 'ca', 'timezone' => 'Europe/Madrid', 'theme_mode' => 'auto', 'birth_date' => '', 'avatar' => $avatar];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function statusOf(string $path, array $params = []): int
    {
        try {
            return $this->send('GET', $path, $params)->response()->getStatusCode();
        } catch (PageNotFoundException) {
            return 404;
        }
    }

    public function testTheProfileSavesAnAvatarUploadedThroughItsField(): void
    {
        $ana  = $this->createUser('ana@example.com', 'user');
        $file = $this->uploadAvatar($ana);
        $this->login('ana@example.com');

        $page = (string) $this->send('GET', 'backend/profile')->response()->getBody();
        $this->assertStringContainsString('data-component="core/forms/file-picker"', $page);

        $this->send('POST', 'backend/profile', $this->profile($file->key))->assertRedirectTo(site_url('backend/profile'));
        $this->assertSame($file->key, Avatars::keyOf((int) $ana->id));
        $this->assertStringContainsString('files/avatar/' . $file->key, (string) $this->send('GET', 'backend/profile')->response()->getBody());

        // A new avatar replaces (and removes) the old file; an empty value removes the avatar.
        $second = $this->uploadAvatar($ana);
        $this->send('POST', 'backend/profile', $this->profile($second->key));
        $this->assertSame($second->key, Avatars::keyOf((int) $ana->id));
        $this->assertNull(service('storage')->find($file->key), 'The replaced avatar is deleted.');

        $this->send('POST', 'backend/profile', $this->profile(''));
        $this->assertNull(Avatars::keyOf((int) $ana->id));
    }

    public function testAForgedAvatarKeyIsRefused(): void
    {
        $bob   = $this->createUser('bob@example.com', 'user');
        $their = $this->uploadAvatar($bob);
        $doc   = service('storage')->storeContents('secret', 'secret.txt', 'private', 'shared/secrets');
        $ana   = $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');

        foreach ([$their->key, $doc->key, '../../etc/passwd', str_repeat('f', 32)] as $forged) {
            $this->send('POST', 'backend/profile', $this->profile($forged));
            $this->assertNull(Avatars::keyOf((int) $ana->id), 'Refused: ' . $forged);
        }

        $this->assertSame(lang('Core.picker.errors.value'), session('errors')['avatar'] ?? null);
    }

    public function testAvatarsAreNotServedToGuestsAndAreCachedForSignedInUsers(): void
    {
        $ana  = $this->createUser('ana@example.com', 'user');
        $file = $this->uploadAvatar($ana);
        db_connect()->table('user_profiles')->insert(['user_id' => $ana->id, 'avatar' => $file->key]);

        $this->assertSame(404, $this->statusOf('files/avatar/' . $file->key), 'Guests get a 404.');

        $this->createUser('bob@example.com', 'user');
        $this->login('bob@example.com');
        $response = $this->send('GET', 'files/avatar/' . $file->key)->response();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(AvatarController::CACHE, $response->getHeaderLine('Cache-Control'));
        $this->assertSame('image/png', explode(';', $response->getHeaderLine('Content-Type'))[0]);
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));

        // Only current avatars: another private file is never served here.
        $doc = service('storage')->storeContents('secret', 'secret.txt', 'private', 'shared/secrets');
        $this->assertSame(404, $this->statusOf('files/avatar/' . $doc->key));
        $this->assertSame(404, $this->statusOf('files/avatar/not-a-key'));
    }

    public function testSignedUrlsWorkWithoutASessionUntilTheyExpire(): void
    {
        $ana  = $this->createUser('ana@example.com', 'user');
        $file = $this->uploadAvatar($ana);
        db_connect()->table('user_profiles')->insert(['user_id' => $ana->id, 'avatar' => $file->key]);

        $url = (string) Avatars::signedUrl($file->key);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame(200, $this->statusOf('files/avatar/' . $file->key, $query));

        $this->assertSame(404, $this->statusOf('files/avatar/' . $file->key, ['expires' => $query['expires'], 'signature' => 'x' . $query['signature']]));
        $this->assertSame(404, $this->statusOf('files/avatar/' . $file->key, ['expires' => (string) ((int) $query['expires'] + 10), 'signature' => $query['signature']]));
        $this->assertFalse(Avatars::validSignature($file->key, $query['expires'], $query['signature'], (int) $query['expires'] + 1), 'Expired');
    }
}
