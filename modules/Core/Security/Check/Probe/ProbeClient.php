<?php

declare(strict_types=1);

namespace Modules\Core\Security\Check\Probe;

use Closure;
use RuntimeException;

/**
 * The dedicated HTTP client of `security:check --probe` (not the OutboundClient, which refuses
 * loopback and private addresses on purpose): requests only to the site itself — the host of
 * the probed URL, which must be the configured base URL host or one of `allowedHostnames` —,
 * HTTPS only except an explicit plain-HTTP request for the redirect check, redirects never
 * followed, a timeout and a 1 MB response limit.
 */
final class ProbeClient
{
    private const MAX_BYTES = 1048576;

    /** @var array<string, string> Cookies the site set (name => value), sent back on later requests. */
    private array $cookies = [];

    /**
     * @param list<string>                                                                                   $hosts     hosts allowed (lower case)
     * @param (Closure(string, string, array<string, string>, ?string): ProbeResponse)|null $transport sends a request (tests)
     */
    public function __construct(
        private readonly array $hosts,
        private readonly int $timeout = 10,
        private readonly ?Closure $transport = null,
    ) {
    }

    public function allows(string $url, bool $allowHttp = false): bool
    {
        $parts  = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host   = strtolower((string) ($parts['host'] ?? ''));

        return ($scheme === 'https' || ($allowHttp && $scheme === 'http'))
            && $host !== ''
            && in_array($host, $this->hosts, true)
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }

    /**
     * @param array<string, string> $headers
     *
     * @throws RuntimeException a URL outside the site, or a network error
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null, bool $allowHttp = false): ProbeResponse
    {
        if (! $this->allows($url, $allowHttp)) {
            throw new RuntimeException('Refused: ' . $url . ' is not this site.');
        }

        if ($this->cookies !== []) {
            $headers['Cookie'] = implode('; ', array_map(static fn (string $name, string $value): string => $name . '=' . $value, array_keys($this->cookies), $this->cookies));
        }

        $response = $this->transport !== null ? ($this->transport)($method, $url, $headers, $body) : $this->curl($method, $url, $headers, $body);

        foreach (explode("\n", $response->header('set-cookie')) as $line) {
            if (preg_match('/^\s*([^=;\s]+)=([^;]*)/', $line, $m) === 1) {
                $this->cookies[$m[1]] = $m[2];
            }
        }

        return $response;
    }

    /**
     * @param array<string, string> $headers
     */
    private function curl(string $method, string $url, array $headers, ?string $body): ProbeResponse
    {
        if (! function_exists('curl_init')) {
            throw new RuntimeException('The curl extension is required by --probe.');
        }

        $received = [];
        $content  = '';
        $handle   = curl_init($url);
        $lines    = [];

        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST   => $method,
            CURLOPT_FOLLOWLOCATION  => false,
            CURLOPT_PROTOCOLS       => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT  => $this->timeout,
            CURLOPT_TIMEOUT         => $this->timeout,
            CURLOPT_HTTPHEADER      => $lines,
            CURLOPT_USERAGENT       => 'kpskeletor-security-check',
            CURLOPT_PROXY           => '',
            CURLOPT_HEADERFUNCTION  => static function ($handle, string $line) use (&$received): int {
                $parts = explode(':', $line, 2);

                if (count($parts) === 2) {
                    $name            = strtolower(trim($parts[0]));
                    $received[$name] = isset($received[$name]) && $name === 'set-cookie' ? $received[$name] . "\n" . trim($parts[1]) : trim($parts[1]);
                }

                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$content): int {
                if (strlen($content) + strlen($chunk) > self::MAX_BYTES) {
                    return 0;
                }

                $content .= $chunk;

                return strlen($chunk);
            },
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $ok     = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($handle);

        if ($ok === false && $status === 0) {
            throw new RuntimeException($url . ': ' . $error);
        }

        return new ProbeResponse($status, $received, $content);
    }
}
