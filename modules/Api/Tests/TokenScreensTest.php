<?php

declare(strict_types=1);

namespace Modules\Api\Tests;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Kpcrudy\Crud;
use Modules\Api\Services\PersonalTokens;
use Modules\Auth\Services\PasswordChanger;

/**
 * Personal tokens in the profile (`api.tokens`, re-authentication, scopes limited to the user's
 * permissions, secret shown once, own tokens only), every user's tokens for administrators
 * (`api.list`/`api.delete`, kpcrudy), the documentation page (`api.docs`), the public OpenAPI
 * document (`Api.publicSpec`) and `api/v1/me`.
 *
 * @internal
 */
final class TokenScreensTest extends ApiTestCase
{
    public function testCreatingATokenShowsTheSecretOnceAndMarksItPersonal(): void
    {
        $user = $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $this->send('POST', 'backend/profile/api-tokens', ['name' => 'ERP', 'scope' => 'some', 'scopes' => ['api.docs'], 'days' => '30'])
            ->assertRedirectTo(site_url('backend/profile/api-tokens'));

        $page = $this->send('GET', 'backend/profile/api-tokens');
        $page->assertOK();
        $this->assertSame(1, preg_match('#id="api-new-token" value="([a-f0-9]{64})"#', (string) $page->response()->getBody(), $match));

        $tokens = (new PersonalTokens())->list($user);
        $this->assertCount(1, $tokens);
        $this->assertTrue($tokens[0]['personal']);
        $this->assertSame(['api.docs'], $tokens[0]['scopes']);
        $this->assertSame(Time::now()->addDays(30)->format('Y-m-d'), substr((string) $tokens[0]['expires_at'], 0, 10));

        $this->send('GET', 'backend/profile/api-tokens')->assertDontSee($match[1]);

        $this->api('GET', 'api/v1/me', null, $match[1])->assertStatus(200);
        $this->assertSame('192.0.2.10', (new PersonalTokens())->list($user)[0]['ip'], 'The address of the last use is recorded.');
    }

    public function testCreatingATokenNeedsRecentReauthentication(): void
    {
        $user = $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        Time::setTestNow(Time::createFromTimestamp(self::NOW + 16 * 60));

        $this->send('POST', 'backend/profile/api-tokens', ['name' => 'ERP', 'scope' => 'all', 'days' => '30'])
            ->assertRedirectTo(site_url('auth/reauth'));
        $this->assertSame([], (new PersonalTokens())->list($user));
    }

    public function testScopesAreLimitedToTheUsersOwnPermissions(): void
    {
        $user = $this->createUser('editor@example.com', 'editor');
        $this->login('editor@example.com');

        $this->send('POST', 'backend/profile/api-tokens', ['name' => 'Escalate', 'scope' => 'some', 'scopes' => ['api.delete'], 'days' => '30']);
        $this->send('POST', 'backend/profile/api-tokens', ['name' => 'Odd', 'scope' => 'all', 'days' => '3650']);
        $this->assertSame([], (new PersonalTokens())->list($user));

        $this->send('POST', 'backend/profile/api-tokens', ['name' => 'Mine', 'scope' => 'some', 'scopes' => ['api.tokens'], 'days' => '7']);
        $this->assertCount(1, (new PersonalTokens())->list($user));
    }

    public function testUsersWithoutTheTokensPermissionCannotCreateTokens(): void
    {
        $user = $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');

        $this->send('GET', 'backend/profile/api-tokens')->assertStatus(403);
        $this->send('POST', 'backend/profile/api-tokens', ['name' => 'Forged', 'scope' => 'all', 'days' => '30'])->assertStatus(403);
        $this->assertSame([], (new PersonalTokens())->list($user));
    }

    public function testUsersRevokeOnlyTheirOwnTokens(): void
    {
        $boss  = $this->createUser('boss@example.com', 'admin');
        $other = $this->createUser('other@example.com', 'admin');
        $mine  = (new PersonalTokens())->create($boss, 'Mine', ['*'], 30);
        $its   = (new PersonalTokens())->create($other, 'Theirs', ['*'], 30);
        $this->login('boss@example.com');

        $this->send('POST', 'backend/profile/api-tokens/' . $its['id'] . '/revoke');
        $this->assertCount(1, (new PersonalTokens())->list($other), 'Another user\'s token is untouched.');

        $this->send('POST', 'backend/profile/api-tokens/' . $mine['id'] . '/revoke');
        $this->assertSame([], (new PersonalTokens())->list($boss));
    }

    public function testAPasswordChangeKeepsPersonalTokens(): void
    {
        $user = $this->createUser('boss@example.com', 'admin');
        (new PersonalTokens())->create($user, 'Mine', ['*'], 30);
        $row = model(UserIdentityModel::class)->where('user_id', $user->id)->where('type', 'access_token')->first();
        $this->assertSame(PasswordChanger::PERSONAL_TOKEN, $row->secret2);
    }

    public function testAdministratorsListAndRevokeEveryonesTokens(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $ana   = $this->createUser('ana@example.com', 'user');
        $token = (new PersonalTokens())->create($ana, 'Phone', ['*'], 30);
        $this->login('boss@example.com');

        $list = $this->send('GET', 'backend/api/tokens');
        $list->assertOK();
        $list->assertSee('Phone');
        $list->assertDontSee($token['token']);

        $this->send('POST', 'backend/api/tokens/delete/' . Crud::maskId($token['id'], 'auth_identities'));
        $this->assertSame([], (new PersonalTokens())->list($ana));
    }

    public function testOrdinaryUsersCannotSeeOrRevokeOthersTokens(): void
    {
        $this->createUser('ana@example.com', 'editor');
        $boss  = $this->createUser('boss@example.com', 'admin');
        $token = (new PersonalTokens())->create($boss, 'Root', ['*'], 30);
        $this->login('ana@example.com');

        $this->send('GET', 'backend/api/tokens')->assertStatus(403);
        $this->send('POST', 'backend/api/tokens/delete/' . Crud::maskId($token['id'], 'auth_identities'))->assertStatus(403);
        $this->assertCount(1, (new PersonalTokens())->list($boss));
    }

    public function testTheDocumentationIsOnlyForApiDocs(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->createUser('ana@example.com', 'editor');

        $this->login('ana@example.com');
        $this->send('GET', 'backend/api/docs')->assertStatus(403);
        $this->send('GET', 'backend/api/docs/openapi.json')->assertStatus(403);

        $this->login('boss@example.com');
        $page = $this->send('GET', 'backend/api/docs');
        $page->assertOK();
        $page->assertSee('try-it.js');
        $spec = json_decode((string) $this->send('GET', 'backend/api/docs/openapi.json')->response()->getBody(), true);
        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertArrayHasKey('/api/v1/auth/token', $spec['paths']);
    }

    public function testThePublicDocumentFollowsTheSetting(): void
    {
        try {
            $this->api('GET', 'api/v1/openapi.json');
            $this->fail('Not published without the setting.');
        } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
            // 404
        }

        setting('Api.publicSpec', true);
        $response = $this->api('GET', 'api/v1/openapi.json');
        $response->assertStatus(200);
        $this->assertSame('3.1.0', $this->json($response)['openapi']);
    }

    public function testMeReturnsTheProfileWithAMaskedId(): void
    {
        $user  = $this->createUser('ana@example.com', 'user');
        $token = $this->tokenFor($user, ['api.docs']);

        $response = $this->api('GET', 'api/v1/me', null, $token);
        $response->assertStatus(200);
        $data = $this->json($response)['data'];

        $this->assertSame('ana@example.com', $data['email']);
        $this->assertSame(Crud::maskId((int) $user->id, 'users'), $data['id']);
        $this->assertSame(['user'], $data['groups']);
        $this->assertSame(['api.docs'], $data['token']['scopes']);
        $this->assertArrayNotHasKey('password_hash', $data);

        $this->api('GET', 'api/v1/me')->assertStatus(401);
        $this->api('GET', 'api/v1/me', null, 'not-a-token')->assertStatus(401);
    }

    public function testMyTokensThroughTheApi(): void
    {
        $user   = $this->createUser('ana@example.com', 'user');
        $token  = $this->tokenFor($user);
        $listed = $this->json($this->api('GET', 'api/v1/me/tokens', null, $token))['data'];

        $this->assertCount(1, $listed);
        $this->assertTrue($listed[0]['current']);
        $this->assertArrayNotHasKey('token', $listed[0]);
    }
}
