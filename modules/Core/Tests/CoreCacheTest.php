<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Cache\CoreCache;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Core\Settings\SettingCaster;

/**
 * One invalidation service for every Core cache scope; typed casting of settings.
 *
 * @internal
 */
final class CoreCacheTest extends CIUnitTestCase
{
    public function testInvalidatingAScopeDropsOnlyItsEntries(): void
    {
        $cache = new CoreCache();
        $calls = 0;
        $value = static function () use (&$calls): int {
            return ++$calls;
        };

        $this->assertSame(1, $cache->remember('menus', 'tree', $value));
        $this->assertSame(1, $cache->remember('menus', 'tree', $value), 'cached');
        $this->assertSame(2, $cache->remember('widgets', 'list', $value));

        $cache->invalidate('menus');

        $this->assertSame(3, $cache->remember('menus', 'tree', $value), 'recomputed after invalidation');
        $this->assertSame(2, $cache->remember('widgets', 'list', $value), 'other scopes untouched');
    }

    public function testNullIsCachedToo(): void
    {
        $cache = new CoreCache();
        $calls = 0;

        $cache->remember('assets', 'k', static function () use (&$calls) {
            $calls++;
            return null;
        });
        $cache->remember('assets', 'k', static function () use (&$calls) {
            $calls++;
            return null;
        });

        $this->assertSame(1, $calls);
    }

    public function testInvalidatorClearsEveryScopeAndFiresTheEvent(): void
    {
        $cache = new CoreCache();
        $cache->remember('permissions', 'x', static fn (): string => 'old');

        $scopes = null;
        Events::on(CacheInvalidator::EVENT, static function (array $s) use (&$scopes): void {
            $scopes = $s;
        });

        (new CacheInvalidator())->invalidate();

        $this->assertSame(CacheInvalidator::SCOPES, $scopes);
        $this->assertSame('new', $cache->remember('permissions', 'x', static fn (): string => 'new'));
    }

    public function testSettingCasting(): void
    {
        $this->assertSame(20, SettingCaster::cast('int', '20'));
        $this->assertSame(0, SettingCaster::cast('int', 'abc'));
        $this->assertSame(1.5, SettingCaster::cast('float', '1.5'));
        $this->assertFalse(SettingCaster::cast('bool', '0'));
        $this->assertTrue(SettingCaster::cast('bool', 'on'));
        $this->assertSame(['a' => 1], SettingCaster::cast('json', '{"a":1}'));
        $this->assertSame([], SettingCaster::cast('json', 'not json'));
        $this->assertSame('12', SettingCaster::cast('string', 12));
        $this->assertSame('#ff0000', SettingCaster::cast('color', '#ff0000'));
        $this->assertNull(SettingCaster::cast('int', null));
    }
}
