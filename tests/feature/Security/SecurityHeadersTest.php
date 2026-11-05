<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Filters;
use Config\SecurityHeaders;
use Modules\Core\Filters\SecurityHeaders as SecurityHeadersFilter;
use Modules\Core\Http\SecureExceptionHandler;
use Throwable;

/**
 * Every response carries the security headers of Config\SecurityHeaders (the Core
 * `securityheaders` filter, never CodeIgniter's `secureheaders`); HSTS only in production
 * (decision D-00-28, docs/security.md).
 *
 * @internal
 */
final class SecurityHeadersTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const EXPECTED = [
        'Referrer-Policy'            => 'strict-origin-when-cross-origin',
        'X-Content-Type-Options'     => 'nosniff',
        'X-Frame-Options'            => 'DENY',
        'Cross-Origin-Opener-Policy' => 'same-origin',
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function paths(): iterable
    {
        yield 'public page' => ['/'];
        yield 'login form' => ['login'];
        yield 'back office (redirect to the login)' => ['backend/profile'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('paths')]
    public function testEveryResponseHasTheHeaders(string $path): void
    {
        $response = $this->get($path);

        foreach (self::EXPECTED as $name => $value) {
            $this->assertSame($value, $response->response()->getHeaderLine($name), $name . ' on ' . $path);
        }

        $policy = $response->response()->getHeaderLine('Permissions-Policy');
        $this->assertStringContainsString('camera=()', $policy);
        $this->assertStringContainsString('microphone=()', $policy);
        $this->assertStringContainsString('geolocation=()', $policy);
        $this->assertFalse($response->response()->hasHeader('Strict-Transport-Security'), 'No HSTS outside production.');
    }

    public function testErrorResponsesHaveTheHeaders(): void
    {
        $handler = config('Exceptions')->handler(404, new \RuntimeException('x'));
        $this->assertInstanceOf(SecureExceptionHandler::class, $handler);

        $inner = new class () implements ExceptionHandlerInterface {
            public ?ResponseInterface $response = null;

            public function handle(Throwable $exception, RequestInterface $request, ResponseInterface $response, int $statusCode, int $exitCode): void
            {
                $this->response = $response;
            }
        };
        (new SecureExceptionHandler($inner))->handle(new PageNotFoundException('x'), service('request'), service('response'), 404, 4);

        $this->assertNotNull($inner->response);
        $this->assertSame('nosniff', $inner->response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('DENY', $inner->response->getHeaderLine('X-Frame-Options'));
    }

    public function testHstsOnlyInProductionAndAtLeastOneYear(): void
    {
        $config = new SecurityHeaders();

        $this->assertArrayNotHasKey('Strict-Transport-Security', SecurityHeadersFilter::headers($config, 'development'));
        $this->assertSame('max-age=31536000; includeSubDomains', SecurityHeadersFilter::headers($config, 'production')['Strict-Transport-Security']);

        $config->hstsMaxAge  = 60;
        $config->hstsPreload = true;
        $this->assertSame('max-age=31536000; includeSubDomains; preload', SecurityHeadersFilter::headers($config, 'production')['Strict-Transport-Security'], 'Never less than a year.');

        $config->hsts = false;
        $this->assertArrayNotHasKey('Strict-Transport-Security', SecurityHeadersFilter::headers($config, 'production'));

        $config->hsts = true;
        $this->assertArrayHasKey('Strict-Transport-Security', SecurityHeadersFilter::headers($config, 'development'));
    }

    public function testConfigurableAndControllerHeadersWin(): void
    {
        $config               = new SecurityHeaders();
        $config->frameOptions = 'SAMEORIGIN';
        $config->extra        = ['X-Permitted-Cross-Domain-Policies' => 'none'];
        $config->referrerPolicy = '';

        $headers = SecurityHeadersFilter::headers($config, 'testing');
        $this->assertSame('SAMEORIGIN', $headers['X-Frame-Options']);
        $this->assertSame('none', $headers['X-Permitted-Cross-Domain-Policies']);
        $this->assertArrayNotHasKey('Referrer-Policy', $headers, 'An empty value is not sent.');

        $response = service('response')->setHeader('X-Frame-Options', 'SAMEORIGIN');
        (new SecurityHeadersFilter())->after(service('request'), $response);
        $this->assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
    }

    public function testCodeIgniterSecureHeadersIsNotUsed(): void
    {
        $filters = new Filters();

        $this->assertArrayNotHasKey('secureheaders', $filters->aliases);
        $this->assertContains('securityheaders', $filters->required['after']);
        $this->assertNotContains('secureheaders', $filters->globals['after']);
    }

    public function testProductionDefaultsForHttpsAndCookies(): void
    {
        // The constructors read ENVIRONMENT (testing here); production is checked by security:check.
        $this->assertFalse(config('App')->forceGlobalSecureRequests);
        $this->assertTrue(config('Cookie')->httponly);
        $this->assertSame('Lax', config('Cookie')->samesite);
        $this->assertStringContainsString("ENVIRONMENT === 'production'", (string) file_get_contents(APPPATH . 'Config/App.php'));
    }
}
