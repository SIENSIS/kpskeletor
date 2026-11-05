<?php

declare(strict_types=1);

namespace Modules\Core\Http;

use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\SecurityHeaders as SecurityHeadersConfig;
use Modules\Core\Filters\SecurityHeaders;
use Throwable;

/**
 * Wraps CodeIgniter's exception handler (Config\Exceptions::handler()) so that error responses
 * (404 of an unknown route, 500…), which never reach the "after" filters, carry the security
 * headers too.
 */
final class SecureExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(private readonly ExceptionHandlerInterface $inner)
    {
    }

    public function handle(Throwable $exception, RequestInterface $request, ResponseInterface $response, int $statusCode, int $exitCode): void
    {
        foreach (SecurityHeaders::headers(config(SecurityHeadersConfig::class)) as $name => $value) {
            if (! $response->hasHeader($name)) {
                $response->setHeader($name, $value);
            }
        }

        $this->inner->handle($exception, $request, $response, $statusCode, $exitCode);
    }
}
