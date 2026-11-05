<?php

declare(strict_types=1);

namespace Modules\Api\Tests;

use Kpcrudy\Crud;
use Modules\Core\Permissions\GrantManager;
use Tests\Support\Api\SampleNotesApi;
use Tests\Support\Crud\SampleModule;

/**
 * A kpapy resource behind the Core's shared resolver (the sample module's `sample-notes`): token
 * scopes intersected with permissions (superadmins too), the same masked ids as the back office,
 * problem+json errors, ETags and `If-Match`, rate limits, CORS closed and no session cookie.
 *
 * @internal
 */
final class ApiResourceTest extends ApiTestCase
{
    private int $note = 0;

    protected function setUp(): void
    {
        parent::setUp();
        SampleModule::install();

        $routes = service('routes')->loadRoutes();
        $routes->getRoutes('GET');
        SampleModule::routes($routes);
        SampleNotesApi::routes($routes);

        (new GrantManager())->setGroupGrants(null, 'user', ['sample-notes.list', 'sample-notes.show']);
        (new GrantManager())->setGroupGrants(null, 'editor', ['sample-notes.*']);

        db_connect()->table('sample_notes')->insert(['title' => 'First note', 'status' => 'draft']);
        $this->note = (int) db_connect()->insertID();
    }

    public function testAListScopedTokenGetsForbiddenOnWrites(): void
    {
        $editor = $this->createUser('editor@example.com', 'editor');
        $token  = $this->tokenFor($editor, ['sample-notes.list']);

        $this->api('GET', 'api/v1/sample/notes', null, $token)->assertStatus(200);

        $response = $this->api('POST', 'api/v1/sample/notes', ['title' => 'New'], $token);
        $response->assertStatus(403);
        $this->assertSame('forbidden', $this->json($response)['code']);
        $this->assertSame(1, db_connect()->table('sample_notes')->countAllResults(), 'Nothing written.');

        $this->api('GET', 'api/v1/sample/notes/' . $this->code(), null, $token)->assertStatus(403);
    }

    public function testASuperadminTokenIsStillLimitedByItsScopes(): void
    {
        $root  = $this->createUser('root@example.com', 'superadmin');
        $token = $this->tokenFor($root, ['sample-notes.list']);

        $this->api('GET', 'api/v1/sample/notes', null, $token)->assertStatus(200);
        $this->api('POST', 'api/v1/sample/notes', ['title' => 'New'], $token)->assertStatus(403);
        $this->api('DELETE', 'api/v1/sample/notes/' . $this->code(), null, $token)->assertStatus(403);

        $full = $this->tokenFor($root);
        $this->api('POST', 'api/v1/sample/notes', ['title' => 'New'], $full)->assertStatus(201);
    }

    public function testScopesNeverAddPermissions(): void
    {
        $reader = $this->createUser('reader@example.com', 'user');
        $token  = $this->tokenFor($reader, ['*']);

        $this->api('GET', 'api/v1/sample/notes', null, $token)->assertStatus(200);
        $this->api('POST', 'api/v1/sample/notes', ['title' => 'New'], $token)->assertStatus(403);
        $this->api('PATCH', 'api/v1/sample/notes/' . $this->code(), ['title' => 'Changed'], $token)->assertStatus(403);
    }

    public function testAWebGrantAppliesToTheApi(): void
    {
        $reader = $this->createUser('reader@example.com', 'user');
        $token  = $this->tokenFor($reader);

        $this->api('POST', 'api/v1/sample/notes', ['title' => 'New'], $token)->assertStatus(403);
        (new GrantManager())->setGroupGrants(null, 'user', ['sample-notes.list', 'sample-notes.show', 'sample-notes.add']);
        $this->api('POST', 'api/v1/sample/notes', ['title' => 'New'], $token)->assertStatus(201);
    }

    public function testARecordHasTheSameMaskedIdInTheWebAndTheApi(): void
    {
        $editor = $this->createUser('editor@example.com', 'editor');
        $body   = $this->json($this->api('GET', 'api/v1/sample/notes', null, $this->tokenFor($editor)));

        $apiId = $body['data'][0]['id'];
        $this->assertNotSame((string) $this->note, $apiId, 'Ids are masked.');
        $this->assertSame($this->code(), $apiId);

        $this->login('editor@example.com');
        $this->send('GET', 'backend/sample/notes')->assertSee('/backend/sample/notes/show/' . $apiId);
    }

    public function testErrorsAreProblemJsonAndTranslated(): void
    {
        $editor = $this->createUser('editor@example.com', 'editor');
        $token  = $this->tokenFor($editor);

        $missing = $this->api('GET', 'api/v1/sample/notes/forged-id', null, $token);
        $missing->assertStatus(404);
        $this->assertSame('application/problem+json', explode(';', $missing->response()->getHeaderLine('Content-Type'))[0]);
        $this->assertSame('not-found', $this->json($missing)['code']);

        $invalid = $this->api('POST', 'api/v1/sample/notes', ['body' => 'No title'], $token);
        $invalid->assertStatus(422);
        $this->assertArrayHasKey('title', $this->json($invalid)['errors']);

        $catalan = $this->api('GET', 'api/v1/sample/notes/forged-id', null, $token, ['Accept-Language' => 'ca']);
        $this->assertNotSame($this->json($missing)['title'], $this->json($catalan)['title'], 'Titles follow the request language.');

        $guest = $this->api('GET', 'api/v1/sample/notes');
        $guest->assertStatus(401);
        $this->assertSame('unauthorized', $this->json($guest)['code']);
    }

    public function testEtagsAndIfMatch(): void
    {
        $editor = $this->createUser('editor@example.com', 'editor');
        $token  = $this->tokenFor($editor);
        $path   = 'api/v1/sample/notes/' . $this->code();

        $show = $this->api('GET', $path, null, $token);
        $etag = $show->response()->getHeaderLine('ETag');
        $this->assertNotSame('', $etag);

        $this->api('GET', $path, null, $token, ['If-None-Match' => $etag])->assertStatus(304);
        $this->api('PATCH', $path, ['title' => 'Without If-Match'], $token)->assertStatus(200);
        $this->api('PATCH', $path, ['title' => 'Stale'], $token, ['If-Match' => $etag])->assertStatus(412);
        $this->assertSame('Without If-Match', db_connect()->table('sample_notes')->where('id', $this->note)->get()->getRow()->title);
    }

    public function testRequestsPerTokenAreLimited(): void
    {
        config('Kpapy')->rateLimit = 2;
        $token = $this->tokenFor($this->createUser('editor@example.com', 'editor'));

        $this->api('GET', 'api/v1/sample/notes', null, $token)->assertStatus(200);
        $this->api('GET', 'api/v1/sample/notes', null, $token)->assertStatus(200);
        $limited = $this->api('GET', 'api/v1/sample/notes', null, $token);
        $limited->assertStatus(429);
        $this->assertSame('too-many-requests', $this->json($limited)['code']);
        $this->assertNotSame('', $limited->response()->getHeaderLine('Retry-After'));
    }

    public function testTheLoginIsLimitedPerIp(): void
    {
        config('Kpapy')->rateLimitLogin = 2;

        $this->api('POST', 'api/v1/auth/token', ['email' => 'a@example.com', 'password' => 'x'])->assertStatus(401);
        $this->api('POST', 'api/v1/auth/token', ['email' => 'b@example.com', 'password' => 'x'])->assertStatus(401);
        $this->api('POST', 'api/v1/auth/token', ['email' => 'c@example.com', 'password' => 'x'])->assertStatus(429);
    }

    public function testCorsIsClosedAndNoSessionCookieIsSet(): void
    {
        $token    = $this->tokenFor($this->createUser('editor@example.com', 'editor'));
        $response = $this->api('GET', 'api/v1/sample/notes', null, $token, ['Origin' => 'https://evil.example']);

        $response->assertStatus(200);
        $this->assertSame('', $response->response()->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame([], $response->response()->getCookies(), 'The API never starts a session.');
    }

    public function testAllowedCorsOriginsComeFromTheSetting(): void
    {
        setting('Api.corsOrigins', ['https://app.example.com', '*']);
        \CodeIgniter\Config\Factories::reset('config');
        $this->assertSame(['https://app.example.com'], config('Kpapy')->corsOrigins, 'Never "*".');

        $token    = $this->tokenFor($this->createUser('editor@example.com', 'editor'));
        $response = $this->api('GET', 'api/v1/sample/notes', null, $token, ['Origin' => 'https://app.example.com']);
        $this->assertSame('https://app.example.com', $response->response()->getHeaderLine('Access-Control-Allow-Origin'));
    }

    private function code(): string
    {
        return Crud::maskId($this->note, 'sample_notes');
    }
}
