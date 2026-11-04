<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Frontend\AssetManifest;
use Modules\Core\Frontend\AssetPublisher;
use Modules\Core\Frontend\ImportMap;
use Modules\Core\Theme\ThemeRegistry;

/**
 * assets:publish: sources → public copies (allowed extensions only, stale files removed), the
 * asset manifest with hashes, `--check`, the development staleness test and the import map.
 *
 * @internal
 */
final class AssetPublisherTest extends CIUnitTestCase
{
    use AssetFixturesTrait;

    protected function setUp(): void
    {
        parent::setUp();
        AssetManifest::reset();
    }

    protected function tearDown(): void
    {
        $this->removeAssetRoot();
        AssetManifest::reset();
        parent::tearDown();
    }

    private function publisher(): AssetPublisher
    {
        return new AssetPublisher(
            $this->assetRoot() . '/public',
            $this->assetRoot() . '/modules',
            new ThemeRegistry($this->assetRoot() . '/themes'),
        );
    }

    private function manifest(): AssetManifest
    {
        AssetManifest::reset();

        return new AssetManifest($this->assetRoot() . '/public/' . AssetManifest::FILE);
    }

    private function fixtures(): void
    {
        $this->assetModule('Core', ['js/app.js' => 'export {}', 'js/ui/toast.js' => 'export const t = 1;', 'css/app.css' => 'a{}']);
        $this->assetModule('TasksDemo', ['js/board.js' => 'export {}', 'img/logo.png' => 'png', 'evil.php' => '<?php echo 1;', 'page.html' => '<p>']);
        $this->assetTheme('default', ['css/theme.css' => 'b{}', 'js/menu.js' => 'export {}']);
    }

    public function testPublishesEverySourceWithAManifest(): void
    {
        $this->fixtures();
        $public  = $this->assetRoot() . '/public';
        $changes = $this->publisher()->publish();

        $this->assertSame(['core' => 3, 'tasks-demo' => 2, 'theme:default' => 2, 'manifest' => 1], $changes);
        $this->assertFileExists($public . '/assets/core/js/ui/toast.js');
        $this->assertFileExists($public . '/assets/modules/tasks-demo/js/board.js');
        $this->assertFileExists($public . '/themes/default/css/theme.css');
        $this->assertFileDoesNotExist($public . '/assets/modules/tasks-demo/evil.php', 'no PHP');
        $this->assertFileDoesNotExist($public . '/assets/modules/tasks-demo/page.html', 'no HTML');

        $manifest = $this->manifest();
        $entry    = $manifest->get('tasks-demo::js/board.js');
        $this->assertNotNull($entry);
        $this->assertSame('assets/modules/tasks-demo/js/board.js', $entry['url']);
        $this->assertSame(substr(hash('sha256', 'export {}'), 0, 12), $entry['hash']);
        $this->assertSame(['app.js', 'ui/toast.js'], array_keys($manifest->filesOf('core', 'js/')));
        $this->assertStringEndsWith('/assets/core/css/app.css?v=' . substr(hash('sha256', 'a{}'), 0, 12), (string) $manifest->url('core::css/app.css'));
        $this->assertNull($manifest->url('core::css/missing.css'));

        $this->assertSame([], $this->publisher()->publish(), 'a second publish changes nothing');
        $this->assertSame([], $this->publisher()->check());
    }

    public function testChangesAndRemovalsAreMirroredAndChecked(): void
    {
        $this->fixtures();
        $publisher = $this->publisher();
        $publisher->publish();
        $public = $this->assetRoot() . '/public';

        file_put_contents($this->assetRoot() . '/modules/Core/Assets/js/app.js', 'export const v = 2;');
        unlink($this->assetRoot() . '/modules/TasksDemo/Assets/img/logo.png');
        file_put_contents($public . '/assets/core/js/stray.js', 'edited by hand');

        $problems = $publisher->check();
        $this->assertContains('assets/core/js/app.js', $problems);
        $this->assertContains('assets/core/js/stray.js', $problems);
        $this->assertContains('assets/modules/tasks-demo/img/logo.png', $problems);
        $this->assertContains(AssetManifest::FILE, $problems);
        $this->assertSame('edited by hand', file_get_contents($public . '/assets/core/js/stray.js'), 'check() writes nothing');

        $changes = $publisher->publish();
        $this->assertSame(2, $changes['core']);
        $this->assertSame(1, $changes['tasks-demo']);
        $this->assertSame('export const v = 2;', file_get_contents($public . '/assets/core/js/app.js'));
        $this->assertFileDoesNotExist($public . '/assets/core/js/stray.js');
        $this->assertDirectoryDoesNotExist($public . '/assets/modules/tasks-demo/img');
        $this->assertSame([], $publisher->check());
    }

    public function testRemovedModulesAndReservedNamesAreNotPublished(): void
    {
        $this->fixtures();
        $this->assetModule('Vendor', ['js/x.js' => 'export {}']);
        $this->publisher()->publish();
        $public = $this->assetRoot() . '/public';

        $this->assertDirectoryDoesNotExist($public . '/assets/modules/vendor', '"vendor" is reserved');

        // The module folder disappears: its published copy goes too.
        foreach (['Assets/js/board.js', 'Assets/img/logo.png', 'Assets/evil.php', 'Assets/page.html', 'Config/Module.php'] as $file) {
            unlink($this->assetRoot() . '/modules/TasksDemo/' . $file);
        }
        rmdir($this->assetRoot() . '/modules/TasksDemo/Assets/js');
        rmdir($this->assetRoot() . '/modules/TasksDemo/Assets/img');
        rmdir($this->assetRoot() . '/modules/TasksDemo/Assets');
        rmdir($this->assetRoot() . '/modules/TasksDemo/Config');
        rmdir($this->assetRoot() . '/modules/TasksDemo');

        $this->publisher()->publish();
        $this->assertDirectoryDoesNotExist($public . '/assets/modules/tasks-demo');
        $this->assertNull($this->manifest()->get('tasks-demo::js/board.js'));
    }

    public function testPublishingOneOwnerKeepsTheOthers(): void
    {
        $this->fixtures();
        $publisher = $this->publisher();
        $publisher->publish();
        file_put_contents($this->assetRoot() . '/themes/default/Assets/css/theme.css', 'c{}');

        $this->assertSame(['theme:default' => 1, 'manifest' => 1], $publisher->publish('theme:default'));
        $this->assertNotNull($this->manifest()->get('core::js/app.js'));
        $this->assertSame(substr(hash('sha256', 'c{}'), 0, 12), $this->manifest()->get('theme:default::css/theme.css')['hash'] ?? null);
    }

    public function testStalenessFollowsTheSources(): void
    {
        $this->fixtures();
        $publisher = $this->publisher();

        $this->assertTrue($publisher->isStale(), 'no manifest yet');
        $publisher->publish();
        $this->assertFalse($publisher->isStale());

        touch($this->assetRoot() . '/modules/Core/Assets/js/app.js', time() + 5);
        clearstatcache();
        $this->assertTrue($publisher->isStale(), 'a newer source');
    }

    public function testImportMapListsEveryJsFileAndChangesWithTheFile(): void
    {
        $this->fixtures();
        $publisher = $this->publisher();
        $publisher->publish();

        $imports = (new ImportMap($this->manifest()))->imports();
        $this->assertSame(['core/app.js', 'core/ui/toast.js', 'tasks-demo/board.js'], array_keys($imports), 'theme files need the active theme');
        $this->assertStringEndsWith('/assets/core/js/ui/toast.js?v=' . substr(hash('sha256', 'export const t = 1;'), 0, 12), $imports['core/ui/toast.js']);
        $before = (new ImportMap($this->manifest()))->json();

        file_put_contents($this->assetRoot() . '/modules/Core/Assets/js/ui/toast.js', 'export const t = 2;');
        $publisher->publish();
        $after = (new ImportMap($this->manifest()))->json();

        $this->assertNotSame($before, $after, 'the import map changes when a file changes');
        $this->assertStringContainsString(substr(hash('sha256', 'export const t = 2;'), 0, 12), $after);
        $this->assertStringNotContainsString('<', $after);
        $this->assertArrayHasKey('imports', json_decode($after, true));
    }

    public function testDevelopmentRefreshRunsBeforeTheFirstRead(): void
    {
        $calls    = 0;
        $manifest = new AssetManifest($this->assetRoot() . '/public/' . AssetManifest::FILE, static function () use (&$calls): void {
            $calls++;
        });

        $this->assertSame([], $manifest->files());
        $manifest->files();
        $this->assertSame(1, $calls, 'once per request');
    }
}
