<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use FilesystemIterator;
use InvalidArgumentException;
use Modules\Core\Frontend\AssetManifest;
use Modules\Core\Frontend\AssetPublisher;
use Modules\Core\Frontend\ImportMap;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The project's real front-end sources: they publish cleanly, every ES module import is a bare
 * specifier that the import map resolves (so every imported file is versioned), the asset()
 * helper and the scripts the layouts print.
 *
 * @internal
 */
final class FrontendAssetsTest extends CIUnitTestCase
{
    use AssetFixturesTrait;

    protected function tearDown(): void
    {
        $this->removeAssetRoot();
        AssetManifest::reset();
        Services::resetSingle('assets');
        parent::tearDown();
    }

    /**
     * @return list<string>
     */
    private static function jsSources(): array
    {
        $files = [];

        foreach ([...glob(ROOTPATH . 'modules/*/Assets/js') ?: [], ...glob(ROOTPATH . 'themes/*/Assets/js') ?: []] as $folder) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)) as $file) {
                /** @var SplFileInfo $file */
                if ($file->isFile() && $file->getExtension() === 'js') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    public function testRealSourcesPublishAndEveryImportResolves(): void
    {
        $public    = $this->assetRoot() . '/public';
        $publisher = new AssetPublisher($public);
        $publisher->publish();

        $this->assertSame([], $publisher->check(), 'published copies and manifest in sync');

        $manifest = new AssetManifest($public . '/' . AssetManifest::FILE);
        $imports  = (new ImportMap($manifest, service('theme')))->imports();

        foreach (['core/app.js', 'core/components.js', 'core/http.js', 'core/ui/confirm.js', 'core/ui/toast.js', 'core/ui/modal.js', 'core/ui/sidebar.js', 'core/ui/tabs-memory.js', 'core/ui/theme-switch.js', 'core/forms/dirty-guard.js', 'core/forms/date.js', 'core/forms/editor.js', 'core/forms/file-picker.js'] as $specifier) {
            $this->assertArrayHasKey($specifier, $imports, $specifier);
        }

        $this->assertNotNull($manifest->get('core::pwa/sw.js'));
        $this->assertArrayNotHasKey('core/pwa/sw.js', $imports, 'the service worker is not an ES module');

        foreach (self::jsSources() as $file) {
            $source = (string) file_get_contents($file);
            preg_match_all('/(?:\bimport\s[^\'";]*?from\s*|\bimport\s*\(\s*|\bimport\s+)[\'"]([^\'"]+)[\'"]/', $source, $matches);

            foreach ($matches[1] as $specifier) {
                $this->assertDoesNotMatchRegularExpression('#^\.{1,2}/#', $specifier, clean_path($file) . ': use bare specifiers (core/…), not relative paths');
                $this->assertArrayHasKey($specifier, $imports, clean_path($file) . " imports {$specifier}, which is not in the import map");
            }

            $this->assertDoesNotMatchRegularExpression('/\beval\s*\(|new\s+Function\s*\(|\.innerHTML\s*=/', $source, clean_path($file) . ': no eval, no Function(), no innerHTML');
        }
    }

    public function testAssetHelper(): void
    {
        $vendor = json_decode((string) file_get_contents(FCPATH . 'assets/vendor/vendor.json'), true);
        $hash   = substr($vendor['bootstrap']['files']['css/bootstrap.min.css'], 0, 12);

        $this->assertSame(base_url('assets/vendor/bootstrap/css/bootstrap.min.css') . '?v=' . $hash, asset('vendor::bootstrap/css/bootstrap.min.css'));
        $this->assertStringStartsWith(base_url('themes/default/css/theme.css') . '?v=', asset('theme::css/theme.css'));
        $this->assertSame(base_url('assets/modules/nowhere/js/x.js'), asset('nowhere::js/x.js'), 'unpublished: no version');
        $this->assertSame(base_url('assets/core/js/missing.js'), asset('core::js/missing.js'));

        foreach (['js/app.js', 'core::../.env', '::x', 'Core::js/app.js'] as $invalid) {
            try {
                asset($invalid);
                $this->fail("{$invalid} accepted");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testAssetHelperUsesTheManifestHash(): void
    {
        $public = $this->assetRoot() . '/public';
        (new AssetPublisher($public))->publish();
        Services::injectMock('assets', new AssetManifest($public . '/' . AssetManifest::FILE));

        $hash = AssetPublisher::hash(ROOTPATH . 'modules/Core/Assets/js/app.js');
        $this->assertSame(base_url('assets/core/js/app.js') . '?v=' . $hash, asset('core::js/app.js'));
    }

    public function testLayoutsPrintTheModeScriptTheImportMapAndTheEntryModule(): void
    {
        $public = $this->assetRoot() . '/public';
        (new AssetPublisher($public))->publish();
        Services::injectMock('assets', new AssetManifest($public . '/' . AssetManifest::FILE));
        service('theme')->reset();

        $html = theme_view('Modules\Core\Views\preview\page', [
            'layout' => theme_layout('public'),
            'title'  => 'Page',
            'themes' => [['slug' => 'default', 'name' => 'Default']],
        ]);
        $html = (string) preg_replace('/<!-- DEBUG-VIEW.*?-->/s', '', $html);

        $mode  = strpos($html, 'core/js/theme-mode.js');
        $style = strpos($html, 'rel="stylesheet"');
        $this->assertNotFalse($mode);
        $this->assertLessThan($style, $mode, 'the mode script runs before the stylesheets');
        $this->assertMatchesRegularExpression('/<script type="importmap" nonce="[^"]+">\{"imports":\{[^}]*"core\/app\.js":/', $html);
        $this->assertMatchesRegularExpression('/<script type="module" src="[^"]+\/assets\/core\/js\/app\.js\?v=[0-9a-f]{12}" nonce="[^"]+"><\/script>/', $html);
        $this->assertMatchesRegularExpression('/<script type="application\/json" id="kp-config">\{"baseUrl":/', $html);
        $this->assertLessThan(strpos($html, 'type="module"'), strpos($html, 'type="importmap"'), 'the import map comes before any module');
        $this->assertStringContainsString('data-theme-mode="auto"', $html);
        $this->assertStringContainsString('data-theme-switch', $html);

        $config = [];
        preg_match('/id="kp-config">(.*?)<\/script>/', $html, $config);
        $data = json_decode($config[1] ?? '', true);
        $this->assertSame('Close', $data['i18n']['ui.close'] ?? null);
    }
}
