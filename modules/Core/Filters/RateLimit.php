<?php

declare(strict_types=1);

namespace Modules\Core\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Security\RateLimiter;
use Modules\Core\Theme\ErrorPage;

/**
 * `ratelimit:<limit>` filter (default limit `backend`, part of the `backend` chain): counts the
 * request per logged-in user, or per IP address for guests, and answers 429 with Retry-After
 * past the limit (JSON when the client asks for it, otherwise the themed error page).
 */
final class RateLimit implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null): ?ResponseInterface
    {
        $limit   = is_array($arguments) && isset($arguments[0]) && $arguments[0] !== '' ? $arguments[0] : 'backend';
        $userId  = auth()->id();
        $subject = $userId !== null ? 'user:' . $userId : 'ip:' . $request->getIPAddress();
        $wait    = (new RateLimiter())->attempt($limit, $subject);

        if ($wait === 0) {
            return null;
        }

        security_log('Core', 'throttled', ['summary' => $request instanceof IncomingRequest ? '/' . ltrim($request->getPath(), '/') : '', 'changes' => ['limit' => $limit]]);

        return self::tooManyRequests($request, $wait);
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    /**
     * The 429 answer with Retry-After.
     */
    public static function tooManyRequests(RequestInterface $request, int $wait): ResponseInterface
    {
        $response = service('response')->setStatusCode(429)->setHeader('Retry-After', (string) max(1, $wait));

        if ($request instanceof IncomingRequest && str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return $response->setJSON(['error' => lang('Core.errors.tooManyRequests.message')]);
        }

        return $response->setContentType('text/html')->setBody(ErrorPage::render(429));
    }
}
