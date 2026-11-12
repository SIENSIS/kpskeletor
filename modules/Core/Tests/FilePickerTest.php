<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Config\Storage as StorageConfig;
use Modules\Auth\Tests\AuthTestCase;
use Modules\Core\Storage\Picker\PickerField;
use Modules\Core\Storage\Picker\StorageBackend;
use Modules\Core\Storage\Picker\UploadToken;
use Modules\Core\Storage\StorageException;
use Modules\Core\Storage\UploadRefused;

/**
 * The file picker of the Core (docs/files.md, "File picker"): signed upload tokens, the field's
 * rules, the endpoints' refusals and the Core's backend (used without the Files module).
 *
 * @internal
 */
final class FilePickerTest extends AuthTestCase
{
    use StorageFixturesTrait;

    private string $previousBackend;

    protected function setUp(): void
    {
        parent::setUp();
        service('routes')->loadRoutes();
        $this->makeStorage();
        $this->previousBackend                         = config(StorageConfig::class)->pickerBackend;
        config(StorageConfig::class)->pickerBackend = StorageBackend::class;
    }

    protected function tearDown(): void
    {
        config(StorageConfig::class)->pickerBackend = $this->previousBackend;
        $this->removeStorageRoot();
        parent::tearDown();
    }

    /**
     * Uploads through the backend as the user (the Storage service records the signed-in user).
     */
    private function pick(\CodeIgniter\Shield\Entities\User $user, PickerField $field, string $contents, string $name): \Modules\Core\Storage\StoredFile
    {
        auth()->login($user);

        try {
            return PickerField::backend()->upload($user, $field->scope($user), TestUploadedFile::fromContents($contents, $name));
        } finally {
            auth()->logout();
        }
    }

    private function field(): PickerField
    {
        return PickerField::make('doc')->mode('upload')->disk('private')->folder('users/{user_id}/docs')->types('txt,pdf')->maxSize(1000);
    }

    public function testTokensAreSignedBoundAndExpire(): void
    {
        $user  = $this->createUser('ana@example.com');
        $scope = $this->field()->scope($user);
        $token = $scope->encode();

        $decoded = UploadToken::decode($token);
        $this->assertNotNull($decoded);
        $this->assertSame((int) $user->id, $decoded->userId);
        $this->assertSame('users/' . $user->id . '/docs', $decoded->folder, '{user_id} is resolved on the server.');
        $this->assertSame(['txt', 'pdf'], $decoded->types);
        $this->assertSame(1000, $decoded->maxSize);

        // Tampered payload (another folder) with the old signature.
        [$payload, $signature] = explode('.', $token);
        $data                  = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);
        $data['f']             = 'shared/elsewhere';
        $forged                = rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=') . '.' . $signature;
        $this->assertNull(UploadToken::decode($forged), 'Changing the folder breaks the signature.');
        $this->assertNull(UploadToken::decode($payload . '.' . strrev($signature)));
        $this->assertNull(UploadToken::decode('garbage'));
        $this->assertNull(UploadToken::decode($token, $scope->expires + 1), 'Expired.');
    }

    public function testFolderVariablesCannotEscapeTheDisk(): void
    {
        $user = $this->createUser('ana@example.com');

        $this->expectException(StorageException::class);
        PickerField::make('x')->folder('users/{user_id}/../../etc')->scope($user);
    }

    public function testTheEndpointsRefuseGuestsAndBadTokens(): void
    {
        $owner = $this->createUser('owner@example.com');
        $token = $this->field()->scope($owner)->encode();

        $this->assertSame(401, $this->send('POST', 'files/picker/upload', ['token' => $token])->response()->getStatusCode(), 'Guests');
        $this->assertSame(401, $this->send('GET', 'files/picker/browse', ['token' => $token])->response()->getStatusCode());

        $this->login('owner@example.com');
        $forged   = substr($token, 0, -2) . (str_ends_with($token, 'AA') ? 'BB' : 'AA');
        $response = $this->send('POST', 'files/picker/upload', ['token' => $forged]);
        $this->assertSame(403, $response->response()->getStatusCode(), 'Tampered token');
        $this->assertSame(lang('Core.picker.errors.token'), json_decode((string) $response->response()->getBody(), true)['message']);
        $this->seeInDatabase('activity_log', ['module' => 'Core', 'action' => 'upload_token_refused', 'type' => 'security']);

        $this->assertSame(403, $this->send('GET', 'files/picker/browse', ['token' => $token])->response()->getStatusCode(), 'An upload-only token cannot browse.');
        $this->assertSame(422, $this->send('POST', 'files/picker/upload', ['token' => $token])->response()->getStatusCode(), 'A valid token without a file');

        // Someone else's token.
        $this->send('POST', 'logout');
        $this->createUser('other@example.com');
        $this->login('other@example.com');
        $this->assertSame(403, $this->send('POST', 'files/picker/upload', ['token' => $token])->response()->getStatusCode(), 'Tokens are bound to their user.');
    }

    public function testBrowsingStaysInsideTheRoots(): void
    {
        $user  = $this->createUser('ana@example.com');
        $field = PickerField::make('cover')->mode('both')->disk('public')->folder('modules/demo')->types('txt');
        $file  = $this->pick($user, $field, 'hello', 'a.txt');
        $this->login('ana@example.com');
        $token = $field->scope($user)->encode();

        $listing = json_decode((string) $this->send('GET', 'files/picker/browse', ['token' => $token])->response()->getBody(), true);
        $this->assertSame('modules/demo', $listing['path']);
        $this->assertSame([$file->key], array_column($listing['files'], 'key'));

        $this->assertSame(404, $this->send('GET', 'files/picker/browse', ['token' => $token, 'path' => 'shared'])->response()->getStatusCode());
        $this->assertSame(404, $this->send('GET', 'files/picker/browse', ['token' => $token, 'path' => 'modules/demo/../../x'])->response()->getStatusCode());
    }

    public function testTheCoreBackendStoresIntoTheTokensFolderWithItsRules(): void
    {
        $user  = $this->createUser('ana@example.com');
        $file  = $this->pick($user, $this->field(), 'Some notes.', 'notes.txt');

        $this->assertSame('private', $file->disk);
        $this->assertStringStartsWith('users/' . $user->id . '/docs/', $file->path);
        $this->assertSame((int) $user->id, $file->uploadedBy);

        $this->expectException(UploadRefused::class);
        $this->pick($user, $this->field(), self::png(), 'image.png');
    }

    public function testTheValueMustComeThroughTheField(): void
    {
        $ana   = $this->createUser('ana@example.com');
        $other = $this->createUser('other@example.com');
        $field = $this->field()->maxFiles(1);
        $mine  = $this->pick($ana, $field, 'mine', 'a.txt');
        $their = $this->pick($other, $field, 'theirs', 'b.txt');
        $loose = service('storage')->storeContents('elsewhere', 'c.txt', 'private', 'shared/x');

        $this->assertTrue($field->accepts($ana, $mine->key));
        $this->assertTrue($field->accepts($ana, ''), 'No file');
        $this->assertTrue($field->accepts($ana, null));
        $this->assertFalse($field->accepts($ana, $their->key), 'Another user\'s upload');
        $this->assertFalse($field->accepts($ana, $loose->key), 'A file of another folder');
        $this->assertFalse($field->accepts($ana, str_repeat('a', 32)), 'Unknown key');
        $this->assertFalse($field->accepts($ana, '../etc/passwd'), 'Not a key');
        $this->assertFalse($field->accepts($ana, $mine->key . ',' . $mine->key), 'Repeated key');
        $this->assertTrue($field->accepts($ana, $their->key, $their->key), 'An unchanged value is kept (e.g. edited by someone else).');

        $two = $this->pick($ana, $field, 'two', 'd.txt');
        $this->assertFalse($field->accepts($ana, $mine->key . ',' . $two->key), 'maxFiles');
        $this->assertTrue((clone $field)->maxFiles(2)->accepts($ana, $mine->key . ',' . $two->key));

        $pdfOnly = $this->field()->types('pdf');
        $this->assertFalse($pdfOnly->accepts($ana, $mine->key), 'A type the field does not allow');
    }

    public function testTheFieldRendersTheComponentWithItsToken(): void
    {
        $user = $this->createUser('ana@example.com');
        $html = $this->field()->render($user, null, ['label' => 'Doc']);

        $this->assertStringContainsString('data-component="core/forms/file-picker"', $html);
        $this->assertStringContainsString('name="doc"', $html);
        $this->assertStringNotContainsString('style=', $html);
        $this->assertStringNotContainsString('users/' . $user->id, $html, 'The folder is only inside the signed token.');
        $this->assertStringNotContainsString('data-component', $this->field()->render($user, null, ['disabled' => true]));
    }
}
