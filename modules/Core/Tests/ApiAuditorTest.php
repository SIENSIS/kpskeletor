<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Router\RouteCollection;
use Config\Database;
use Config\Services;
use Modules\Api\Tests\ApiTestCase;
use Modules\Core\Config\ModuleManifest;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Permissions\ApiAuditor;
use Modules\Core\Permissions\CrudPermissions;
use Tests\Support\Api\SampleNotesApi;

/**
 * `ApiAuditor` (`permissions:check`, session 26): every kpapy resource route is declared in a
 * manifest's `$api`, renders a `Kpapy\Resource` and asks only about declared operations.
 *
 * @internal
 */
final class ApiAuditorTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CrudPermissions::reset();
    }

    public function testTheShippedResourcesPass(): void
    {
        $this->assertSame([], (new ApiAuditor())->audit($this->routes(), $this->manifests()));
    }

    public function testAnUndeclaredResourceAndUndeclaredOperationsFail(): void
    {
        // The table only: the "Sample" module's permissions are not in the catalogue here.
        $forge = Database::forge();
        $forge->addField([
            'id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'title'  => ['type' => 'VARCHAR', 'constraint' => 150],
            'body'   => ['type' => 'TEXT', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'draft'],
        ]);
        $forge->addPrimaryKey('id');
        $forge->createTable('sample_notes', true);
        $routes = $this->routes();
        SampleNotesApi::routes($routes);

        $problems = implode("\n", (new ApiAuditor())->audit($routes, $this->manifests()));

        $this->assertStringContainsString("API api/v1/sample/notes: the resource is not declared in any manifest's \$api.", $problems);
        $this->assertStringContainsString('API api/v1/sample/notes: sample-notes asks about operations the manifest does not declare: add, delete, edit, list, show.', $problems);
    }

    public function testAHandlerThatRendersNoResourceFails(): void
    {
        $routes = $this->routes();
        \Kpapy\Resource::routes($routes, 'api/v1/me-again', '\Modules\Api\Controllers\Me::index', ['filter' => 'kpapy']);

        $problems = (new ApiAuditor())->audit($routes, $this->manifests());

        $this->assertContains('API api/v1/me-again: the handler does not render a Kpapy\Resource.', $problems);
    }

    private function routes(): RouteCollection
    {
        return Services::routes(false)->loadRoutes();
    }

    /**
     * @return array<string, ModuleManifest>
     */
    private function manifests(): array
    {
        $manifests = [];

        foreach ((new ModuleDiscovery())->discover() as $name => $module) {
            if ($module->manifest !== null) {
                $manifests[$name] = $module->manifest;
            }
        }

        return $manifests;
    }
}
