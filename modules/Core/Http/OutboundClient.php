<?php

declare(strict_types=1);

namespace Modules\Core\Http;

use Closure;
use Config\Outbound;

/**
 * The only way to call an external HTTP service (SSRF protection; docs/outbound-requests.md).
 *
 * - HTTPS only, ports of `Config\Outbound::$ports` (443), no user information in the URL;
 * - the host must be in the allow-list (`OutboundAllowList`);
 * - the host is resolved and **every** address must be public (no private, loopback,
 *   link-local… ranges); the connection is pinned to the checked address;
 * - redirects are followed by hand (at most `maxRedirects`), each hop checked again;
 * - connect and total timeouts, a maximum response size;
 * - every request is logged (method, host, path, status or the refusal).
 *
 * Never call it on regular page requests: scheduled tasks, console commands and explicit admin
 * actions only. Refusals and failures throw OutboundException.
 *
 * ```php
 * $response = (new OutboundClient())->get('https://api.github.com/repos/acme/news/releases/latest', [
 *     'Accept' => 'application/vnd.github+json',
 * ]);
 * $release = $response->json();
 * ```
 */
final class OutboundClient
{
    private readonly Outbound $config;

    private readonly OutboundAllowList $allowList;

    private readonly OutboundTransport $transport;

    /** @var Closure(string): list<string> */
    private readonly Closure $resolver;

    /**
     * @param (Closure(string): list<string>)|null $resolver host => IP addresses (default: DNS A and AAAA)
     */
    public function __construct(
        ?Outbound $config = null,
        ?OutboundAllowList $allowList = null,
        ?OutboundTransport $transport = null,
        ?Closure $resolver = null,
    ) {
        $this->config    = $config ?? config(Outbound::class);
        $this->allowList = $allowList ?? new OutboundAllowList($this->config);
        $this->transport = $transport ?? new CurlTransport($this->config->proxy);
        $this->resolver  = $resolver ?? self::dnsResolver(...);
    }

    /**
     * @param array<string, string> $headers
     */
    public function get(string $url, array $headers = []): OutboundResponse
    {
        return $this->request('GET', $url, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public function post(string $url, string $body, array $headers = []): OutboundResponse
    {
        return $this->request('POST', $url, $headers, $body);
    }

    /**
     * @param array<string, string> $headers
     *
     * @throws OutboundException
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): OutboundResponse
    {
        $method  = strtoupper($method);
        $headers = ['User-Agent' => $this->config->userAgent] + $headers;

        for ($hop = 0; ; $hop++) {
            try {
                [, $port, $ip] = $this->check($url);
                $response           = $this->transport->send($method, $url, $headers, $body, $ip, $port, $this->config->connectTimeout, $this->config->timeout, $this->config->maxBytes);
            } catch (OutboundException $e) {
                $this->log($method, $url, 'refused (' . $e->reason . ')');

                throw $e;
            }

            $this->log($method, $url, (string) $response->status);

            if (! $response->isRedirect()) {
                return $response;
            }

            if ($hop >= $this->config->maxRedirects) {
                $this->log($method, $url, 'refused (redirects)');

                throw new OutboundException(OutboundException::REASON_REDIRECTS, 'Too many redirects.');
            }

            $url = self::absolute((string) $response->header('location'), $url);

            if ($response->status === 303 || (in_array($response->status, [301, 302], true) && $method === 'POST')) {
                $method = 'GET';
                $body   = null;
            }

        }
    }

    /**
     * Validates a URL and resolves its host.
     *
     * @return array{0: string, 1: int, 2: string} host, port, address to connect to
     *
     * @throws OutboundException
     */
    public function check(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || preg_match('/[\x00-\x20\x7F\\\\]/', $url) === 1) {
            throw new OutboundException(OutboundException::REASON_URL, 'Invalid URL.');
        }

        if (strtolower($parts['scheme']) !== 'https') {
            throw new OutboundException(OutboundException::REASON_SCHEME, 'Only HTTPS is allowed.');
        }

        $port = (int) ($parts['port'] ?? 443);

        if (! in_array($port, $this->config->ports, true)) {
            throw new OutboundException(OutboundException::REASON_PORT, 'Port ' . $port . ' is not allowed.');
        }

        $host = strtolower(trim($parts['host'], '[]'));

        if (! $this->allowList->allows($host)) {
            throw new OutboundException(OutboundException::REASON_HOST, 'The host ' . $host . ' is not in the outbound allow-list.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($this->resolver)($host);

        if ($addresses === []) {
            throw new OutboundException(OutboundException::REASON_DNS, 'The host ' . $host . ' could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (! IpGuard::isPublic($address)) {
                throw new OutboundException(OutboundException::REASON_PRIVATE, 'The host ' . $host . ' resolves to a private or reserved address.');
            }
        }

        return [$host, $port, $addresses[0]];
    }

    /**
     * A Location header made absolute against the URL of the response.
     */
    public static function absolute(string $location, string $base): string
    {
        $location = trim($location);

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location) === 1) {
            return $location;
        }

        $parts  = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');

        if (str_starts_with($location, '//')) {
            return ($parts['scheme'] ?? 'https') . ':' . $location;
        }

        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }

        $path = $parts['path'] ?? '/';
        $dir  = substr($path, 0, (int) strrpos($path, '/') + 1);

        return $origin . ($dir === '' ? '/' : $dir) . $location;
    }

    /**
     * @return list<string>
     */
    private static function dnsResolver(string $host): array
    {
        $addresses = [];

        foreach ([DNS_A => 'ip', DNS_AAAA => 'ipv6'] as $type => $field) {
            $records = @dns_get_record($host, $type);

            foreach (is_array($records) ? $records : [] as $record) {
                if (isset($record[$field]) && is_string($record[$field])) {
                    $addresses[] = $record[$field];
                }
            }
        }

        if ($addresses === []) {
            $v4 = @gethostbynamel($host);
            $addresses = is_array($v4) ? $v4 : [];
        }

        return array_values(array_unique($addresses));
    }

    private function log(string $method, string $url, string $result): void
    {
        $parts = parse_url($url);

        $host = is_array($parts) ? (string) ($parts['host'] ?? '?') : '?';
        $path = is_array($parts) ? (string) ($parts['path'] ?? '/') : '';
        $data = ['resource' => 'outbound', 'summary' => $method . ' ' . $host . $path, 'changes' => ['result' => $result]];

        if (str_starts_with($result, 'refused')) {
            security_log('Core', 'outbound_refused', $data);
        } else {
            activity_log('Core', 'outbound', $data);
        }
    }
}
