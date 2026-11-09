<?php

declare(strict_types=1);

namespace Tests\Feature\Crud;

use CodeIgniter\Router\RouteCollection;
use Config\Services;
use Kpcrudy\Crud;
use Modules\Auth\Tests\AuthTestCase;
use Modules\Core\Crud\CrudAuditor;
use Modules\Core\Crud\CrudFactory;
use Modules\Core\Permissions\PermissionAudit;
use Modules\Core\Permissions\UndeclaredPermissionException;
use Tests\Support\Crud\SampleModule;
use Tests\Support\Crud\SampleNotes;

/**
 * `permissions:check` compares every kpcrudy CRUD with the catalogue (`CrudAuditor`, D-07-05).
 *
 * @internal
 */
final class CrudAuditorTest extends AuthTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SampleModule::install();
    }

    public function testTheSampleCrudsMatchTheCatalogue(): void
    {
        $routes = $this->routes();
        SampleModule::routes($routes);

        $auditor = new CrudAuditor();
        $this->assertCount(9, $auditor->crudRoutes($routes), 'One entry per CRUD (the two samples, the Api module\'s token list, the Core\'s notifications and scheduled tasks, the Activity module\'s log and "My activity", the Users module\'s users and groups), not per kpcrudy route.');
        $this->assertSame([], $auditor->audit($routes));
        $this->assertFalse(CrudFactory::inspecting(), 'Inspection is switched off afterwards.');
    }

    public function testUndeclaredOperationsAreReported(): void
    {
        $routes = $this->routes();
        CrudFactory::routes($routes, 'sample-notes', 'backend/sample/archive', '\\' . SampleNotes::class . '::undeclared');

        $this->assertSame(
            ['GET backend/sample/archive: sample-notes asks about operations the manifest does not declare: archive.'],
            (new CrudAuditor())->audit($routes),
        );
    }

    public function testCrudsOutsideTheFactoryAreReported(): void
    {
        $routes = $this->routes();
        Crud::routes($routes, 'backend/sample/raw', '\\' . SampleNotes::class . '::raw', ['filter' => 'can:sample-notes.list']);
        CrudFactory::routes($routes, 'sample-mine', 'backend/sample/wrong', '\\' . SampleNotes::class . '::raw');

        $problems = (new CrudAuditor())->audit($routes);

        $this->assertCount(2, $problems);
        $this->assertStringContainsString('GET backend/sample/raw: register the CRUD with CrudFactory::routes()', $problems[0]);
        $this->assertStringContainsString('GET backend/sample/wrong: the controller does not build its CRUD with CrudFactory::make()', $problems[1]);
    }

    public function testPermissionsCheckIncludesTheCrudComparison(): void
    {
        $routes = $this->routes();
        CrudFactory::routes($routes, 'sample-notes', 'backend/sample/archive', '\\' . SampleNotes::class . '::undeclared');

        $result = (new PermissionAudit(routes: $routes))->run();

        $this->assertContains('GET backend/sample/archive: sample-notes asks about operations the manifest does not declare: archive.', $result['errors']);
    }

    public function testAnUndeclaredOperationFailsLoudlyAtRunTime(): void
    {
        $crud = CrudFactory::make('sample-notes')->setTable('sample_notes')
            ->addOperation('archive', static fn (): null => null);

        $this->expectException(UndeclaredPermissionException::class);
        $this->expectExceptionMessage('archive');
        $crud->isAllowed('list');
    }

    private function routes(): RouteCollection
    {
        return Services::routes(false)->loadRoutes();
    }
}
