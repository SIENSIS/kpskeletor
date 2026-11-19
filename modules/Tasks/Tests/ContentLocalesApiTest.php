<?php

declare(strict_types=1);

namespace Modules\Tasks\Tests;

use Kpcrudy\Crud;
use Modules\Api\Tests\ApiTestCase;
use Modules\Core\Permissions\CrudPermissions;
use Modules\Core\Tests\ContentLocalesTrait;

/**
 * The projects API follows the site's languages (session 37): a fourth language is answered with
 * the fallback to the site's default language and a project created without the default language
 * is refused.
 *
 * @internal
 */
final class ContentLocalesApiTest extends ApiTestCase
{
    use ContentLocalesTrait;
    use TasksFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        CrudPermissions::reset();
        $this->useContentLocales();
    }

    public function testProjectsInTheNewLanguageAndTheDefaultOneRequired(): void
    {
        $boss  = $this->createUser('boss@example.com', 'admin');
        $token = $this->tokenFor($boss);
        $id    = $this->project('Nou web');
        db_connect()->table('task_project_translations')->insert(['project_id' => $id, 'locale' => 'es', 'name' => 'Nueva web', 'description' => '']);

        $data = $this->json($this->api('GET', 'api/v1/tasks/projects/' . Crud::maskId($id, 'task_projects') . '?lang=fr', null, $token))['data'];
        $this->assertSame('Nueva web', $data['name'], 'No French text: the default language, not Catalan.');

        $refused = $this->api('POST', 'api/v1/tasks/projects?lang=ca', ['name' => ['ca' => 'Només català'], 'slug' => 'nomes-catala', 'color' => '#0d6efd'], $token);
        $this->assertSame(422, $refused->response()->getStatusCode(), 'Without Spanish: refused.');

        $created = $this->api('POST', 'api/v1/tasks/projects', ['name' => ['es' => 'Solo español'], 'slug' => 'solo-espanol', 'color' => '#0d6efd'], $token);
        $this->assertSame(201, $created->response()->getStatusCode(), (string) $created->response()->getBody());
    }
}
