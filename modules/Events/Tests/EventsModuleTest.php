<?php

declare(strict_types=1);

namespace Modules\Events\Tests;

use CodeIgniter\Database\Migration;
use Config\Database;
use Modules\Core\Demo\DemoDataManager;
use Modules\Core\Modules\ManifestValidator;
use Modules\Events\Config\Module;
use Modules\Events\Menu\CalendarLink;

/**
 * The Events module: manifest, permissions, migrations, the menu link type, the configuration
 * class and the demo data.
 *
 * @internal
 */
final class EventsModuleTest extends EventsTestCase
{
    public function testManifestIsValid(): void
    {
        $this->assertSame([], (new ManifestValidator())->validate(new Module(), dirname(__DIR__)));
        $this->assertFileDoesNotExist(dirname(__DIR__) . '/Config/Events.php', 'CodeIgniter would load it as an event-listener file (D-00-04).');
        $this->assertSame('dayGridMonth', config(\Modules\Events\Config\Calendar::class)->defaultView);
    }

    public function testThePermissionsAndDefaultGrants(): void
    {
        $user   = $this->createUser('ana@example.com', 'user');
        $editor = $this->createUser('edi@example.com', 'editor');
        $admin  = $this->createUser('boss@example.com', 'admin');

        $this->assertTrue($user->can('events-calendar.view'));
        $this->assertFalse($user->can('events-events.list'));
        $this->assertTrue($editor->can('events-events.add'));
        $this->assertFalse($editor->can('events-events.all'));
        $this->assertTrue($admin->can('events-events.all'));
        $this->assertTrue($admin->can('events-categories.delete'));
    }

    public function testTheCalendarMenuLink(): void
    {
        db_connect()->table('event_categories')->insert(['slug' => 'cursos', 'position' => 1, 'active' => 1]);
        db_connect()->table('event_category_translations')->insert(['category_id' => db_connect()->insertID(), 'locale' => 'ca', 'name' => 'Cursos']);
        $link = new CalendarLink();

        $this->assertSame('events.calendar', $link->key());
        $this->assertArrayHasKey('cursos', $link->options());
        $this->assertSame(site_url('calendar'), $link->url('all'));
        $this->assertSame(site_url('calendar') . '?category=cursos', $link->url('cursos'));
        $this->assertNull($link->url('gone'));
    }

    public function testMigrationsGoDownAndUpAgain(): void
    {
        $files      = glob(dirname(__DIR__) . '/Database/Migrations/*.php') ?: [];
        $migrations = [];

        foreach ($files as $file) {
            $class = 'Modules\Events\Database\Migrations\\' . preg_replace('/^[\d_-]+/', '', basename($file, '.php'));

            if (! class_exists($class, false)) {
                require_once $file;
            }

            $migrations[] = new $class(Database::forge('tests'));
        }

        $this->assertCount(6, $migrations);

        foreach (array_reverse($migrations) as $migration) {
            \assert($migration instanceof Migration);
            $migration->down();
        }

        $this->db->resetDataCache();
        $this->assertFalse($this->db->tableExists('events'));

        foreach ($migrations as $migration) {
            $migration->up();
        }

        $this->db->resetDataCache();

        foreach (['event_categories', 'event_category_translations', 'events', 'event_translations', 'event_audience', 'event_reminders'] as $table) {
            $this->assertTrue($this->db->tableExists($table), $table);
        }
    }

    public function testDemoDataLoadsAndIsRemovedCompletely(): void
    {
        $this->createUser('usuari@example.com', 'user');
        $manager = new DemoDataManager(null, 'development');
        $tables  = ['event_categories', 'event_category_translations', 'events', 'event_translations', 'event_audience'];

        $this->assertTrue($manager->load('Events'));
        $this->assertSame(8, db_connect()->table('events')->countAllResults());
        $this->assertSame(2, db_connect()->table('event_audience')->countAllResults(), 'The editors\' event and the one for usuari@.');

        $manager->remove('Events');

        foreach ($tables as $table) {
            $this->assertSame(0, db_connect()->table($table)->countAllResults(), $table);
        }
    }

    public function testADisabledModuleAnswers404(): void
    {
        $this->loginWith('events-events.list');
        db_connect()->table('modules')->where('name', 'Events')->update(['enabled' => 0]);
        (new \Modules\Core\Cache\CoreCache())->invalidate('modules');

        $this->assertNotFound('GET', 'backend/calendar');
        $this->assertNotFound('GET', 'calendar');
    }
}
