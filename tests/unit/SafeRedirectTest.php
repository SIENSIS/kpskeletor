<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Modules\Core\Security\SafeRedirect;

/**
 * Redirects only to internal paths (`safe_redirect()`): an external `return` URL is refused.
 *
 * @internal
 */
final class SafeRedirectTest extends CIUnitTestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function urls(): iterable
    {
        yield 'absolute path' => ['/backend/news?page=2', true];
        yield 'relative path' => ['backend/news', true];
        yield 'query only' => ['?page=2', true];
        yield 'own host' => ['https://example.com/backend', true];
        yield 'allowed host' => ['https://www.example.com/x', true];
        yield 'other host' => ['https://evil.example.org/', false];
        yield 'protocol relative' => ['//evil.example.org/', false];
        yield 'backslashes' => ['/\\evil.example.org', false];
        yield 'javascript' => ['javascript:alert(1)', false];
        yield 'data' => ['data:text/html,x', false];
        yield 'user information' => ['https://example.com@evil.example.org/', false];
        yield 'credentials on own host' => ['https://evil:x@example.com/', false];
        yield 'control characters' => ["/back\nend", false];
        yield 'tab in scheme' => ["java\tscript:alert(1)", false];
        yield 'ftp' => ['ftp://example.com/', false];
        yield 'empty' => ['', false];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('urls')]
    public function testOnlyInternalTargets(string $url, bool $internal): void
    {
        $config                   = new App();
        $config->baseURL          = 'https://example.com/';
        $config->allowedHostnames = ['www.example.com'];

        $this->assertSame($internal, (new SafeRedirect($config))->isInternal($url));
        $this->assertSame($internal ? trim($url) : '/backend', (new SafeRedirect($config))->target($url, '/backend'));
    }

    public function testHelperRedirectsExternalUrlsToTheFallback(): void
    {
        $this->assertSame(site_url('backend'), safe_redirect('https://evil.example.org/', '/backend')->getHeaderLine('Location'));
        $this->assertSame(site_url('backend/news?page=2'), safe_redirect('/backend/news?page=2')->getHeaderLine('Location'));
        $this->assertSame(site_url(''), safe_redirect(null)->getHeaderLine('Location'));
        $this->assertSame(site_url(''), safe_redirect('//evil.example.org', '//evil.example.org')->getHeaderLine('Location'), 'An external fallback is refused too.');
    }
}
