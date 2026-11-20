<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Modules\Auth\Tests\AuthTestCase;
use Modules\Core\Groups\GroupRegistry;
use Modules\Core\Groups\GroupTransfer;
use Modules\Core\Permissions\PermissionCatalogue;

/**
 * `groups:export` / `groups:import` (D-42-04): groups and, optionally, their grants go from one
 * site to another without users; import creates and updates groups, replaces grants only with
 * permissions that exist here, never touches superadmin and is logged.
 *
 * @internal
 */
final class GroupTransferTest extends AuthTestCase
{
    public function testTheExportHasTheGroupsAndNoUsers(): void
    {
        $this->createUser('ana@example.com', 'editor');

        $export = (new GroupTransfer())->export(true);
        $json   = (string) json_encode($export);

        $this->assertSame(GroupTransfer::FORMAT, $export['format']);
        $this->assertSame(['superadmin', 'admin', 'editor', 'user'], array_column($export['groups'], 'name'));
        $this->assertArrayNotHasKey('grants', $export['groups'][0], 'superadmin holds every permission.');
        $this->assertSame((new PermissionCatalogue())->grants('admin'), $export['groups'][1]['grants']);
        $this->assertStringNotContainsString('ana@example.com', $json);
        $this->assertArrayNotHasKey('grants', (new GroupTransfer())->export()['groups'][1]);
    }

    public function testTheImportCreatesUpdatesAndReplacesGrantsThatExist(): void
    {
        $catalogue = new PermissionCatalogue();
        $known     = array_keys($catalogue->stored())[0];
        $before    = $catalogue->grants('admin');

        $report = (new GroupTransfer())->import(['format' => 1, 'groups' => [
            ['name' => 'editor', 'title' => 'Coordinador', 'description' => 'Coordinació', 'two_factor' => 'required'],
            ['name' => 'alumnes', 'title' => 'Alumnes', 'grants' => [$known, 'library-books.list']],
            ['name' => 'superadmin', 'title' => 'Superadmin', 'grants' => []],
            ['name' => 'Bad Name'],
        ]], true);

        $this->assertSame(['alumnes'], $report['created']);
        $this->assertSame(['editor', 'superadmin'], $report['updated']);
        $this->assertSame(['alumnes: library-books.list'], $report['skippedPermissions']);
        $this->assertCount(1, $report['errors']);

        $registry = new GroupRegistry();
        $editor   = $registry->find('editor');
        $alumnes  = $registry->find('alumnes');
        $this->assertNotNull($editor);
        $this->assertNotNull($alumnes);
        $this->assertSame('Coordinador', $editor['title']);
        $this->assertSame(GroupRegistry::REQUIRED, $editor['two_factor']);
        $this->assertFalse($alumnes['reserved']);
        $this->assertContains($known, $catalogue->grants('alumnes'));
        $this->assertSame($before, $catalogue->grants('admin'), 'Groups not in the file keep their grants.');

        $this->seeInDatabase('activity_log', ['module' => 'Core', 'action' => 'groups_imported', 'type' => 'security']);
    }

    public function testAnExportImportedAgainChangesNoGrant(): void
    {
        $catalogue = new PermissionCatalogue();
        $before    = ['admin' => $catalogue->grants('admin'), 'editor' => $catalogue->grants('editor'), 'user' => $catalogue->grants('user')];
        $transfer  = new GroupTransfer();

        $report = $transfer->import($transfer->export(true), true);

        $this->assertSame([], $report['skippedPermissions'], 'Wildcard grants (`news-api.*`) are kept.');
        $this->assertSame($before, ['admin' => $catalogue->grants('admin'), 'editor' => $catalogue->grants('editor'), 'user' => $catalogue->grants('user')]);
    }

    public function testWithoutGrantsOnlyTheGroupsChange(): void
    {
        $catalogue = new PermissionCatalogue();
        $before    = $catalogue->grants('user');

        (new GroupTransfer())->import(['format' => 1, 'groups' => [['name' => 'user', 'title' => 'Alumnat', 'grants' => []]]]);

        $this->assertSame($before, $catalogue->grants('user'));
        $this->assertSame('Alumnat', (new GroupRegistry())->find('user')['title'] ?? null);
    }

    public function testAFileThatIsNotAnExportIsRefused(): void
    {
        $report = (new GroupTransfer())->import(['groups' => 'nope']);

        $this->assertSame([lang('Core.groups.transfer.invalidFile')], $report['errors']);
        $this->assertSame([], $report['created']);
    }
}
