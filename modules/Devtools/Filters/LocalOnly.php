<?php

declare(strict_types=1);

namespace Modules\Devtools\Filters;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Route filter `devtools` (lock 4 of docs/devtools.md, D-41-03): the request answers 404 unless
 * the client address (`REMOTE_ADDR`, never a proxy header) is a loopback address **and** the
 * `Host` is `localhost` or a loopback address. `php spark serve` (http://localhost:8080) passes.
 */
final class LocalOnly implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     *
     * @return ResponseInterface|null
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $remote = service('superglobals')->server('REMOTE_ADDR');
        $host   = service('superglobals')->server('HTTP_HOST');

        if (! self::isLocal(is_string($remote) ? $remote : null, is_string($host) ? $host : null)) {
            throw PageNotFoundException::forPageNotFound();
        }

        return null;
    }

    /**
     * @param list<string>|null $arguments
     *
     * @return ResponseInterface|null
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    public static function isLocal(?string $remoteAddress, ?string $host): bool
    {
        if ($remoteAddress === null || $host === null || ! self::isLoopback($remoteAddress)) {
            return false;
        }

        $host = strtolower(trim($host));

        if (preg_match('/^\[([0-9a-f:.]+)\](?::\d{1,5})?$/', $host, $match) === 1) {
            $name = $match[1];                       // [::1]:8080
        } elseif (preg_match('/^([a-z0-9.-]+)(?::\d{1,5})?$/', $host, $match) === 1) {
            $name = $match[1];                       // localhost:8080, 127.0.0.1
        } else {
            return false;
        }

        return $name === 'localhost' || self::isLoopback($name);
    }

    /** 127.0.0.0/8, ::1 and ::ffff:127.x.x.x. */
    public static function isLoopback(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return str_starts_with($address, '127.');
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return false;
        }

        $packed = inet_pton($address);

        if ($packed === false) {
            return false;
        }

        if ($packed === inet_pton('::1')) {
            return true;
        }

        // IPv4-mapped: ::ffff:127.0.0.1
        return str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff") && ord($packed[12]) === 127;
    }
}
