<?php

declare(strict_types=1);

namespace Tests\Feature\Crud;

use CodeIgniter\Test\TestResponse;
use Kpcrudy\Crud;
use Modules\Auth\Tests\AuthTestCase;
use Modules\Core\Crud\CrudFactory;
use Modules\Core\Permissions\GrantManager;
use Tests\Support\Crud\SampleModule;

/**
 * Acceptance criterion 4 on the sample CRUD (test-only "Sample" module, session 09): a user with
 * only `list` and `show` sees no write buttons and every write URL and custom operation is
 * refused, also through forged POSTs; a user without the `author` permission never sees the
 * author field (list, details, export); `where()` limits "my notes"; ids in URLs are masked.
 *
 * @internal
 */
final class SampleCrudTest extends AuthTestCase
{
    private int $mine = 0;

    private int $other = 0;

    protected function setUp(): void
    {
        parent::setUp();
        SampleModule::install();

        $routes = service('routes')->loadRoutes();
        $routes->getRoutes('GET'); // runs route discovery first
        SampleModule::routes($routes);

        $grants = new GrantManager();
        $grants->setGroupGrants(null, 'user', ['sample-notes.list', 'sample-notes.show', 'sample-mine.list', 'sample-mine.show', 'sample-mine.edit']);
        $grants->setGroupGrants(null, 'editor', [
            'sample-notes.list', 'sample-notes.show', 'sample-notes.add', 'sample-notes.edit',
            'sample-notes.delete', 'sample-notes.export', 'sample-notes.publish',
        ]);
        $grants->setGroupGrants(null, 'admin', ['sample-notes.*']);

        $reader = $this->createUser('reader@example.com', 'user');
        $this->createUser('editor@example.com', 'editor');
        $this->createUser('boss@example.com', 'admin');

        $db = db_connect();
        $db->table('sample_notes')->insert(['title' => 'Reader note', 'author' => 'Secret Author', 'status' => 'draft', 'owner_id' => $reader->id]);
        $this->mine = (int) $db->insertID();
        $db->table('sample_notes')->insert(['title' => 'Someone else', 'author' => 'Secret Author', 'status' => 'draft', 'owner_id' => 999]);
        $this->other = (int) $db->insertID();
    }

    public function testReadOnlyUserSeesTheListWithoutWriteButtons(): void
    {
        $this->login('reader@example.com');
        $response = $this->send('GET', 'backend/sample/notes');

        $response->assertOK();
        $body = html_entity_decode((string) $response->response()->getBody());
        $this->assertStringContainsString('Reader note', $body);
        $this->assertStringContainsString('Notes', $body, 'The page header shows the CRUD title.');
        $this->assertStringContainsString('/backend/sample/notes/show/' . $this->code($this->mine), $body);

        foreach (['/backend/sample/notes/add', '/backend/sample/notes/edit/', '/backend/sample/notes/delete/', '/backend/sample/notes/publish/', '/backend/sample/notes/export'] as $url) {
            $this->assertStringNotContainsString($url, $body, "No {$url} button for a read-only user.");
        }
    }

    public function testReadOnlyUserIsRefusedEveryWriteUrlAndOperation(): void
    {
        $this->login('reader@example.com');
        $code = $this->code($this->mine);

        foreach (['add', 'edit/' . $code, 'export', 'publish/' . $code] as $operation) {
            $this->assertRefused($this->send('GET', 'backend/sample/notes/' . $operation), "GET {$operation}");
        }
    }

    public function testForgedPostsChangeNothing(): void
    {
        $this->login('reader@example.com');
        $code = $this->code($this->mine);

        $this->assertRefused($this->send('POST', 'backend/sample/notes/add', ['title' => 'Forged', 'status' => 'draft']), 'POST add');
        $this->assertRefused($this->send('POST', 'backend/sample/notes/edit/' . $code, ['title' => 'Changed']), 'POST edit');
        $this->assertRefused($this->send('POST', 'backend/sample/notes/delete/' . $code), 'POST delete');
        $this->assertRefused($this->send('POST', 'backend/sample/notes/publish/' . $code), 'POST publish');
        $this->assertRefused($this->send('POST', 'backend/sample/notes/bulk', ['action' => 'delete', 'ids' => [$code]]), 'POST bulk delete');

        $row = db_connect()->table('sample_notes')->where('id', $this->mine)->get()->getRowArray();
        $this->assertSame('Reader note', $row['title']);
        $this->assertSame('draft', $row['status']);
        $this->assertSame(2, db_connect()->table('sample_notes')->countAllResults());
    }

    public function testTheCustomOperationRunsForAUserWithItsPermission(): void
    {
        $this->login('editor@example.com');

        $this->send('POST', 'backend/sample/notes/publish/' . $this->code($this->mine))->assertRedirect();

        $this->assertSame('published', db_connect()->table('sample_notes')->where('id', $this->mine)->get()->getRow()->status);
        $this->assertSame('Published.', $_SESSION['success'] ?? null, 'kpcrudy messages use the layout flash keys.');
    }

    public function testTheAuthorFieldIsHiddenWithoutItsPermission(): void
    {
        $this->login('editor@example.com');
        $code = $this->code($this->mine);

        $list = (string) $this->send('GET', 'backend/sample/notes')->response()->getBody();
        $show = (string) $this->send('GET', 'backend/sample/notes/show/' . $code)->response()->getBody();
        $form = (string) $this->send('GET', 'backend/sample/notes/edit/' . $code)->response()->getBody();
        $csv  = (string) $this->send('GET', 'backend/sample/notes/export')->response()->getBody();

        $this->assertStringContainsString('Reader note', $list.$show.$csv);

        foreach (['list' => $list, 'details' => $show, 'form' => $form, 'export' => $csv] as $where => $html) {
            $this->assertStringNotContainsString('Secret Author', $html, "No author in the {$where}.");
        }

        $this->assertStringNotContainsString('name="author"', $form);

        // A forged author is not saved.
        $this->send('POST', 'backend/sample/notes/edit/' . $code, ['title' => 'Edited', 'body' => '', 'status' => 'draft', 'author' => 'Forged']);
        $row = db_connect()->table('sample_notes')->where('id', $this->mine)->get()->getRowArray();
        $this->assertSame('Edited', $row['title']);
        $this->assertSame('Secret Author', $row['author']);
    }

    public function testTheAuthorFieldIsShownWithItsPermission(): void
    {
        $this->login('boss@example.com');

        $this->assertStringContainsString('Secret Author', (string) $this->send('GET', 'backend/sample/notes')->response()->getBody());
        $this->assertStringContainsString('Secret Author', (string) $this->send('GET', 'backend/sample/notes/export')->response()->getBody());
    }

    public function testWhereLimitsMyNotesEvenWithAForgedId(): void
    {
        $this->login('reader@example.com');

        $list = (string) $this->send('GET', 'backend/sample/mine')->response()->getBody();
        $this->assertStringContainsString('Reader note', $list);
        $this->assertStringNotContainsString('Someone else', $list);

        $other = $this->code($this->other);
        $this->assertStringNotContainsString('Someone else', (string) $this->send('GET', 'backend/sample/mine/show/' . $other)->response()->getBody());
        $this->send('POST', 'backend/sample/mine/edit/' . $other, ['title' => 'Taken', 'body' => '']);
        $this->assertSame('Someone else', db_connect()->table('sample_notes')->where('id', $this->other)->get()->getRow()->title);
    }

    public function testIdsInUrlsAreMaskedAndRealIdsAreNotFound(): void
    {
        $this->login('boss@example.com');

        $this->assertNotSame((string) $this->mine, $this->code($this->mine));
        $response = $this->send('GET', 'backend/sample/notes/show/' . $this->mine);
        $this->assertStringNotContainsString('Secret Author', (string) $response->response()->getBody());
    }

    public function testGuestsAndUsersWithoutListGetNothing(): void
    {
        $this->send('GET', 'backend/sample/notes')->assertRedirectTo('/login');

        (new GrantManager())->setGroupGrants(null, 'user', []);
        $this->login('reader@example.com');
        $this->send('GET', 'backend/sample/notes')->assertStatus(403);
    }

    public function testRoutesAreNamedAfterTheResourceWithTheBackendChainAndCan(): void
    {
        $routes = service('routes');

        $this->assertSame('/backend/sample/notes', route_to('sample-notes'));
        $this->assertContains('backend', $routes->getFiltersForRoute('backend/sample/notes', 'GET'));
        $this->assertContains('can:sample-notes.list', $routes->getFiltersForRoute('backend/sample/notes', 'GET'));
        $this->assertContains('can:sample-notes.list', $routes->getFiltersForRoute('backend/sample/notes', 'POST'));
    }

    public function testTheFactoryResolvesThroughTheCatalogue(): void
    {
        $crud = CrudFactory::make('sample-notes');

        $this->assertSame('sample-notes', $crud->getResource());
        $this->expectException(\InvalidArgumentException::class);
        CrudFactory::make('Sample Notes');
    }

    private function code(int $id): string
    {
        return Crud::maskId($id, 'sample_notes');
    }

    /**
     * kpcrudy refuses with a redirect to the list and an error message, which `flashKeys` puts
     * in the layout's `error` key (a GET to a POST-only operation and a bulk action the user
     * cannot use are "invalid requests").
     */
    private function assertRefused(TestResponse $response, string $what): void
    {
        $this->assertTrue($response->isRedirect(), "{$what}: redirected to the list.");
        $this->assertSame(site_url('backend/sample/notes'), $response->response()->getHeaderLine('Location'), $what);
        $this->assertSame('new', $_SESSION['__ci_vars']['error'] ?? null, "{$what}: an error message from this request.");
        $this->assertContains($_SESSION['error'] ?? null, [lang('Kpcrudy.notAllowed'), lang('Kpcrudy.invalidRequest')], "{$what}: refused with an error message.");
        $this->assertArrayNotHasKey('success', $_SESSION, "{$what}: no success message.");
    }
}
