<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Modules\Core\Theme\ErrorPage;

/**
 * 403/404/500 pages rendered by the theme, the plain fallback and the theme preview's guard.
 *
 * @internal
 */
final class ErrorPagesTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testThemedErrorPages(): void
    {
        foreach ([403 => 'forbidden', 404 => 'notFound', 500 => 'serverError'] as $code => $key) {
            $html = ErrorPage::render($code);

            $this->assertStringContainsString('class="layout-blank', $html, (string) $code);
            $this->assertStringContainsString(lang('Core.errors.' . $key . '.title'), $html);
            $this->assertStringContainsString('>' . $code . '<', $html);
        }
    }

    public function testMessagesAreEscaped(): void
    {
        $this->assertStringContainsString('&lt;script&gt;', ErrorPage::render(404, '<script>alert(1)</script>'));
    }

    public function testPlainFallback(): void
    {
        $html = ErrorPage::plain(500);

        $this->assertStringContainsString(lang('Core.errors.serverError.title'), $html);
        $this->assertStringNotContainsString('<link', $html);
        $this->assertStringNotContainsString('<style', $html);
    }

    public function testCanFilterAnswersWithTheThemedPageOrJson(): void
    {
        $filter  = new \Modules\Core\Filters\Can();
        $request = service('request');

        $response = $filter->before($request, ['news-posts.list']);
        $this->assertNotNull($response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString(lang('Core.errors.forbidden.title'), (string) $response->getBody());

        $request->setHeader('Accept', 'application/json');
        $response = $filter->before($request, ['news-posts.list']);
        $this->assertNotNull($response);
        $this->assertSame(['error' => lang('Core.forbidden')], json_decode((string) $response->getBody(), true));
    }

    public function testThemePreviewIsNotAvailableOutsideDevelopment(): void
    {
        $config          = config('Theme');
        $config->preview = true;

        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->get('theme-preview/public');
    }
}
