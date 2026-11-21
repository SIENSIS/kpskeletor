<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Theme\ZoneContent;
use RuntimeException;

/**
 * Theme zones (D-47-01; docs/themes.md, "Zones"): the default theme declares
 * `public.after_header` and `public.before_footer`; `theme_zone()` prints what the listeners of
 * the Core event `themeZone` add, in order; an undeclared zone and a failing listener give an
 * empty zone (a broken block never takes a page down).
 *
 * @internal
 */
final class ThemeZonesTest extends CIUnitTestCase
{
    /** @var list<callable> the listeners registered by the modules (restored after each test) */
    private array $saved = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->saved = Events::listeners('themeZone');
    }

    protected function tearDown(): void
    {
        Events::removeAllListeners('themeZone');

        foreach ($this->saved as $listener) {
            Events::on('themeZone', $listener);
        }

        service('theme')->reset();
        parent::tearDown();
    }

    public function testTheDefaultThemeDeclaresItsZones(): void
    {
        $this->assertSame(['public.after_header', 'public.before_footer'], array_keys(service('theme')->zones('public')));
    }

    public function testListenersFillAZoneInOrder(): void
    {
        Events::removeAllListeners('themeZone');
        Events::on('themeZone', static function (ZoneContent $content): void {
            if ($content->zone === 'public.before_footer') {
                $content->add('<p>second</p>', 20);
                $content->add('<p>first</p>', 10);
                $content->add('   ', 0);
            }
        });

        $this->assertSame("<p>first</p>\n<p>second</p>", service('theme')->zone('public.before_footer', 'public'));
        $this->assertSame('', service('theme')->zone('public.after_header', 'public'));
        $this->assertSame('', service('theme')->zone('public.undeclared', 'public'), 'Only zones the theme declares.');
    }

    public function testAFailingListenerLeavesTheZoneEmpty(): void
    {
        Events::removeAllListeners('themeZone');
        Events::on('themeZone', static function (ZoneContent $content): void {
            $content->add('<p>half</p>');

            throw new RuntimeException('broken block');
        });

        $this->assertSame('', service('theme')->zone('public.before_footer', 'public'));
    }
}
