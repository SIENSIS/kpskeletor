<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\HTTP\ContentSecurityPolicy;
use CodeIgniter\HTTP\Response;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\App;
use Config\ContentSecurityPolicy as CspConfig;
use Config\Services;
use Modules\Core\Config\ModuleManifest;
use Modules\Core\Filters\ContentSecurity;
use Modules\Core\Frontend\CspViewScanner;
use Modules\Core\Registry\DeclarationRegistry;

/**
 * The strict CSP with nonces on every response, its report-only mode, the sources modules add
 * in their manifest, and the check of the project's views (no inline code, handlers or styles).
 *
 * @internal
 */
final class ContentSecurityPolicyTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        // One policy per request: the response and the nonce helpers share it.
        Services::resetSingle('csp');
        Services::resetSingle('response');
    }

    protected function tearDown(): void
    {
        Services::resetSingle('csp');
        parent::tearDown();
    }

    /**
     * @return array<string, string> directive => sources
     */
    private static function directives(string $header): array
    {
        $directives = [];

        foreach (array_filter(array_map('trim', explode(';', $header))) as $part) {
            [$name, $value]    = explode(' ', $part, 2) + [1 => ''];
            $directives[$name] = $value;
        }

        return $directives;
    }

    public function testStrictPolicyIsOnByDefault(): void
    {
        $this->assertTrue(config(App::class)->CSPEnabled);
        $this->assertFalse(config(CspConfig::class)->reportOnly);
    }

    public function testEveryPageCarriesTheStrictPolicyWithMatchingNonces(): void
    {
        $result = $this->get('/');
        $result->assertStatus(200);

        // Response::send() finalizes the policy; feature tests do not send.
        $response = $result->response();
        $response->getCSP()->finalize($response);
        $header = $response->getHeaderLine('Content-Security-Policy');
        $this->assertNotSame('', $header);
        $this->assertStringNotContainsString('unsafe-inline', $header);
        $this->assertStringNotContainsString('unsafe-eval', $header);

        $csp = self::directives($header);
        $this->assertSame("'self'", $csp['default-src']);
        $this->assertSame("'self' data:", $csp['img-src']);
        $this->assertSame("'self'", $csp['connect-src']);
        $this->assertSame("'none'", $csp['object-src']);
        $this->assertSame("'self'", $csp['base-uri']);
        $this->assertSame("'self'", $csp['form-action']);
        $this->assertSame("'none'", $csp['frame-ancestors']);
        $this->assertSame("'none'", $csp['script-src-attr'], 'no event handler attributes');
        $this->assertSame("'none'", $csp['style-src-attr'], 'no style attributes');
        $this->assertMatchesRegularExpression("/^'self' 'nonce-[A-Za-z0-9+\\/=]+'$/", $csp['script-src']);
        $this->assertMatchesRegularExpression("/^'self' 'nonce-[A-Za-z0-9+\\/=]+'$/", $csp['script-src-elem']);

        preg_match("/'nonce-([^']+)'/", $csp['script-src'], $nonce);
        $body = (string) $response->getBody();
        $this->assertStringContainsString('<script type="importmap" nonce="' . $nonce[1] . '">', $body, 'the body uses the header nonce');
        $this->assertStringNotContainsString('{csp-script-nonce}', $body);
    }

    public function testReportOnlyMode(): void
    {
        $config             = new CspConfig();
        $config->reportOnly = true;
        $csp                = new ContentSecurityPolicy($config);
        $response           = new Response(new App());

        $csp->finalize($response);

        $this->assertFalse($response->hasHeader('Content-Security-Policy'));
        $this->assertStringContainsString("default-src 'self'", $response->getHeaderLine('Content-Security-Policy-Report-Only'));
    }

    public function testModuleSourcesAreAddedSafely(): void
    {
        $csp = new ContentSecurityPolicy(new CspConfig());
        ContentSecurity::apply($csp, [
            'frame-src'  => ['https://www.youtube-nocookie.com'],
            'script-src' => ['https://cdn.example.com', "'unsafe-inline'", '*'],
            'img-src'    => ['https://tiles.example.com', 'data:'],
            'object-src' => ["'none'"],
            'unknown'    => ['https://x.example.com'],
        ]);
        $response = new Response(new App());
        $csp->finalize($response);

        $policy = self::directives($response->getHeaderLine('Content-Security-Policy'));
        $this->assertSame('https://www.youtube-nocookie.com', $policy['frame-src']);
        $this->assertSame("'self' https://cdn.example.com", $policy['script-src']);
        $this->assertSame("'self' https://cdn.example.com", $policy['script-src-elem'], 'elements are checked against script-src-elem');
        $this->assertSame("'self' data: https://tiles.example.com", $policy['img-src']);
        $this->assertStringNotContainsString('unsafe-inline', $response->getHeaderLine('Content-Security-Policy'));
        $this->assertStringNotContainsString(' *', $response->getHeaderLine('Content-Security-Policy'));
        $this->assertStringNotContainsString('x.example.com', $response->getHeaderLine('Content-Security-Policy'));
    }

    public function testManifestSourcesBecomeDeclarations(): void
    {
        $manifest = new class () extends ModuleManifest {
            public string $name = 'Video';
            public array $csp   = ['frame-src' => ['https://www.youtube-nocookie.com']];
        };

        $declarations = (new DeclarationRegistry())->build(['Video' => $manifest]);

        $this->assertSame(['https://www.youtube-nocookie.com'], $declarations['Video|csp|frame-src']['definition']);
    }

    public function testProjectViewsHaveNoInlineCode(): void
    {
        $this->assertSame([], (new CspViewScanner())->scan());
    }

    public function testScannerRules(): void
    {
        $scanner = new CspViewScanner();
        $view    = <<<'HTML'
            <div class="x" onclick="go()">a</div>
            <p style="color:red">b</p>
            <a href="javascript:void(0)">c</a>
            <script>alert(1)</script>
            <script <?= csp_script_nonce() ?>>alert(2)</script>
            <style>p{}</style>
            <script src="<?= asset('core::js/app.js') ?>">alert(3)</script>
            HTML;
        $violations = $scanner->check($view, 'modules/X/Views/x.php');

        $this->assertCount(7, $violations, implode("\n", $violations));
        $this->assertContains('modules/X/Views/x.php:1: ' . lang('Core.csp.handler'), $violations);
        $this->assertContains('modules/X/Views/x.php:4: ' . lang('Core.csp.inlineScript'), $violations);

        $allowed = <<<'HTML'
            <script src="<?= esc(asset('core::js/app.js')) ?>" <?= csp_script_nonce() ?>></script>
            <script type="application/json" id="data"><?= json_encode($data) ?></script>
            <script type="importmap" <?= csp_script_nonce() ?>><?= import_map()->json() ?></script>
            <a href="<?= esc($url) ?>" data-confirm="<?= esc($question) ?>" class="<?= $active ? 'active' : '' ?>">ok</a>
            <p>Text about onclick= and style= in prose is fine.</p>
            HTML;
        $this->assertSame([], $scanner->check($allowed, 'modules/X/Views/y.php'));

        $this->assertCount(1, $scanner->check('<style <?= csp_style_nonce() ?>>a{}</style>', 'modules/X/Views/z.php'), 'only the theme head may print a <style>');
        $this->assertSame([], $scanner->check('<style <?= csp_style_nonce() ?>>a{}</style>', 'themes/corp/Views/partials/head.php'));
        $this->assertCount(1, $scanner->check('<script type="importmap">{}</script>', 'modules/X/Views/z.php'), 'an import map needs the nonce');
    }
}
