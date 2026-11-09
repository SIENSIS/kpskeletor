<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Kpapy\Auth\Context;
use Kpapy\PermissionResolver as ApiResolver;
use Kpapy\Resource;
use Kpcrudy\Crud;
use Kpcrudy\PermissionResolver as CrudResolver;
use Modules\Core\Permissions\CrudPermissions;
use Modules\Core\Permissions\GrantManager;
use Modules\Core\Permissions\UndeclaredPermissionException;
use ReflectionMethod;

/**
 * The one resolver shared by kpcrudy and kpapy.
 *
 * @internal
 */
final class CrudPermissionsTest extends RbacTestCase
{
    protected function tearDown(): void
    {
        Context::reset();
        parent::tearDown();
    }

    public function testItImplementsBothInterfacesWithAnObjectParameter(): void
    {
        $resolver = new CrudPermissions();

        $this->assertInstanceOf(CrudResolver::class, $resolver);
        $this->assertInstanceOf(ApiResolver::class, $resolver);
        $this->assertSame('object', (string) (new ReflectionMethod(CrudPermissions::class, 'allows'))->getParameters()[3]->getType());
    }

    public function testSearchFiltersAndBulkArePartOfList(): void
    {
        $this->assertSame('news-posts.list', CrudPermissions::permission('news-posts', 'search'));
        $this->assertSame('news-posts.list', CrudPermissions::permission('news-posts', 'filters'));
        $this->assertSame('news-posts.list', CrudPermissions::permission('news-posts', 'bulk'));
        $this->assertSame('news-posts.publish', CrudPermissions::permission('news-posts', 'publish'));
    }

    public function testACrudAsksTheCatalogueForTheSessionUser(): void
    {
        (new GrantManager())->setGroupGrants(null, 'editor', ['news-categories.list']);
        $this->actAs($this->createUser('editor@example.com', 'editor'));
        $crud     = $this->categoriesCrud();
        $resolver = new CrudPermissions();

        $this->assertTrue($resolver->allows('news-categories', 'list', null, $crud));
        $this->assertTrue($resolver->allows('news-categories', 'search', null, $crud));
        $this->assertTrue($resolver->allows('news-categories', 'bulk', null, $crud));
        $this->assertFalse($resolver->allows('news-categories', 'delete', ['id' => 1], $crud));

        $crud->setPermissionResolver($resolver);
        $this->assertTrue($crud->isAllowed('filters'));
        $this->assertFalse($crud->isAllowed('add'));
    }

    public function testRecordRulesStillRestrict(): void
    {
        (new GrantManager())->setGroupGrants(null, 'editor', ['news-categories.edit']);
        $this->actAs($this->createUser('editor@example.com', 'editor'));
        $crud = $this->categoriesCrud()->setPermissionResolver(new CrudPermissions())
            ->setPermission('edit', static fn (?array $row): bool => $row === null || $row['name'] !== 'Locked');

        $this->assertTrue($crud->isAllowed('edit', ['name' => 'Open']));
        $this->assertFalse($crud->isAllowed('edit', ['name' => 'Locked']));
    }

    public function testSuperadminsPassAndGuestsAreDenied(): void
    {
        $crud = $this->categoriesCrud();

        $this->actAs(null);
        $this->assertFalse((new CrudPermissions())->allows('news-categories', 'list', null, $crud));

        $this->actAs($this->createUser('root@example.com', 'superadmin'));
        $this->assertTrue((new CrudPermissions())->allows('news-categories', 'delete', null, $crud));
    }

    public function testAnExplicitUserNeverReadsTheSession(): void
    {
        $this->actAs($this->createUser('root@example.com', 'superadmin'));
        $crud = $this->categoriesCrud();

        $this->assertFalse(CrudPermissions::forUser($this->createUser('ana@example.com'))->allows('news-categories', 'list', null, $crud));
        $this->assertFalse(CrudPermissions::forUser(null)->allows('news-categories', 'list', null, $crud));
    }

    public function testApiResourcesUseTheUserOfTheKpapyContext(): void
    {
        (new GrantManager())->setGroupGrants(null, 'editor', ['news-categories.list']);
        $this->actAs($this->createUser('root@example.com', 'superadmin')); // the session is ignored
        $api = (new Resource())->setTable('modules')->setResource('news-categories');

        Context::set($this->createUser('editor@example.com', 'editor'));
        $this->assertTrue((new CrudPermissions())->allows('news-categories', 'search', null, $api));
        $this->assertFalse((new CrudPermissions())->allows('news-categories', 'delete', null, $api));

        Context::reset();
        $this->assertFalse((new CrudPermissions())->allows('news-categories', 'list', null, $api));
    }

    public function testUndeclaredOperationsFailLoudlyOutsideProduction(): void
    {
        $crud = (new Crud())->setTable('modules')->setResource('news-categories')
            ->addOperation('archive', static fn (): null => null);

        $this->assertSame(['archive'], (new CrudPermissions())->undeclared('news-categories', $crud), 'The News manifest declares the rest (session 17).');

        $this->expectException(UndeclaredPermissionException::class);
        $this->expectExceptionMessage('archive');
        (new CrudPermissions())->allows('news-categories', 'list', null, $crud);
    }

    /**
     * A CRUD over the `modules` table named like the fake News categories (no trash, no export).
     */
    private function categoriesCrud(): Crud
    {
        return (new Crud())->setTable('modules')->setResource('news-categories')->unsetExport();
    }
}
