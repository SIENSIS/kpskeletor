<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Modules\Core\Cache\CoreCache;
use Modules\Core\Widgets\QuickLinks;
use Modules\Core\Widgets\ScheduledTasks;
use Modules\Core\Widgets\Widget;
use Modules\Core\Widgets\WidgetOptions;
use Modules\Core\Widgets\WidgetRegistry;
use Modules\Core\Widgets\WidgetRenderer;
use Modules\Core\Widgets\WidgetSizes;

/**
 * The widget framework: registry (broken declarations skipped), sizes, options and the
 * renderer's failure isolation.
 *
 * @internal
 */
final class WidgetsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected function setUp(): void
    {
        parent::setUp();
        (new CoreCache())->invalidate('widgets');
        (new CoreCache())->invalidate('menus');
    }

    private function declare(string ...$classes): void
    {
        foreach ($classes as $class) {
            $this->db->table('module_declarations')->insert(['module' => 'Core', 'kind' => 'widget', 'key' => $class, 'definition' => '[]']);
        }

        (new CoreCache())->invalidate('widgets');
    }

    public function testTheRegistrySkipsBrokenDeclarations(): void
    {
        $this->declare(ScheduledTasks::class, 'Modules\Core\Missing\Widget', \stdClass::class, QuickLinks::class, QuickLinks::class . 'Nope');

        $registry = new WidgetRegistry();

        $this->assertSame(['core.scheduled-tasks', 'core.quick-links'], array_keys($registry->all()));
        $this->assertSame('Core', $registry->module('core.quick-links'));
        $this->assertNull($registry->get('core.nothing'));
    }

    public function testSizes(): void
    {
        $widget = new QuickLinks();

        $this->assertSame(WidgetSizes::SMALL, WidgetSizes::pick($widget, null));
        $this->assertSame(WidgetSizes::FULL, WidgetSizes::pick($widget, 'full'));
        $this->assertSame(WidgetSizes::SMALL, WidgetSizes::pick($widget, 'huge'));
        $this->assertSame('col-12', WidgetSizes::columns('full'));
    }

    public function testOptionsFallBackToTheirDefaults(): void
    {
        $options = new WidgetOptions();
        $widget  = new ScheduledTasks();

        $this->assertSame(['limit' => 5], $options->defaults($widget));
        $this->assertSame(['limit' => 7], $options->resolve($widget, ['limit' => '7', 'unknown' => 1]));
        $this->assertSame(['limit' => 5], $options->resolve($widget, ['limit' => '99']), 'Out of range: default.');
        $this->assertSame(['limit' => 5], $options->resolve($widget, ['limit' => 'many']));
    }

    public function testOptionTypesAreValidated(): void
    {
        $widget = new class () extends Widget {
            public function id(): string
            {
                return 'core.test';
            }

            public function title(): string
            {
                return 'Test';
            }

            public function settings(): array
            {
                return [
                    'on'    => ['type' => 'bool', 'default' => true, 'label' => 'x'],
                    'mode'  => ['type' => 'select', 'default' => 'a', 'options' => ['a' => 'x', 'b' => 'y'], 'label' => 'x'],
                    'title' => ['type' => 'string', 'default' => '', 'label' => 'x'],
                ];
            }

            public function render(array $options): string
            {
                return '';
            }
        };
        $options = new WidgetOptions();

        $ok = $options->validate($widget, ['mode' => 'b', 'title' => '  Hello ']);
        $this->assertSame([], $ok['errors']);
        $this->assertSame(['on' => false, 'mode' => 'b', 'title' => 'Hello'], $ok['values'], 'An unticked checkbox is false.');

        $bad = $options->validate($widget, ['on' => 'maybe', 'mode' => 'c', 'title' => "two\nlines"]);
        $this->assertSame(['on', 'mode', 'title'], array_keys($bad['errors']));
    }

    public function testAFailingWidgetIsIsolated(): void
    {
        $widget = new class () extends Widget {
            public function id(): string
            {
                return 'core.broken';
            }

            public function title(): string
            {
                throw new \RuntimeException('title');
            }

            public function render(array $options): string
            {
                throw new \RuntimeException('render');
            }
        };

        $html = (new WidgetRenderer())->card($widget, [], WidgetSizes::MEDIUM);

        $this->assertStringContainsString(lang('Core.widgets.failed'), $html);
        $this->assertStringContainsString('data-widget="core.broken"', $html);
        $this->assertStringNotContainsString('RuntimeException', $html);
    }
}
