<?php

declare(strict_types=1);

namespace Modules\Core\Http;

/**
 * cURL transport: HTTPS only, certificate verification on, the host pinned to the checked
 * address (`CURLOPT_RESOLVE`, so DNS cannot change between the check and the connection), no
 * redirects, the body cut at the size limit.
 */
final class CurlTransport implements OutboundTransport
{
    public function __construct(private readonly string $proxy = '')
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body, string $ip, int $port, int $connectTimeout, int $timeout, int $maxBytes): OutboundResponse
    {
        $host     = (string) parse_url($url, PHP_URL_HOST);
        $received = '';
        $tooLarge = false;
        $response = [];

        $handle = curl_init($url);

        if ($handle === false) {
            throw new OutboundException(OutboundException::REASON_TRANSPORT, 'cURL could not start.');
        }

        $lines = [];

        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST   => $method,
            CURLOPT_HTTPHEADER      => $lines,
            CURLOPT_RESOLVE         => [$host . ':' . $port . ':' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)],
            CURLOPT_PROTOCOLS       => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION  => false,
            CURLOPT_SSL_VERIFYPEER  => true,
            CURLOPT_SSL_VERIFYHOST  => 2,
            CURLOPT_CONNECTTIMEOUT  => $connectTimeout,
            CURLOPT_TIMEOUT         => $timeout,
            CURLOPT_PROXY           => $this->proxy,
            CURLOPT_NOPROXY         => $this->proxy === '' ? '*' : '',
            CURLOPT_HEADERFUNCTION  => static function ($ch, string $line) use (&$response): int {
                $parts = explode(':', $line, 2);

                if (count($parts) === 2) {
                    $response[strtolower(trim($parts[0]))] = trim($parts[1]);
                } elseif (str_starts_with($line, 'HTTP/')) {
                    $response = []; // a new response (100 Continue)
                }

                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION   => static function ($ch, string $chunk) use (&$received, &$tooLarge, $maxBytes): int {
                if (strlen($received) + strlen($chunk) > $maxBytes) {
                    $tooLarge = true;

                    return 0; // aborts the transfer
                }

                $received .= $chunk;

                return strlen($chunk);
            },
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $ok     = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($handle);
        unset($handle);

        if ($tooLarge) {
            throw new OutboundException(OutboundException::REASON_TOO_LARGE, 'The response is larger than ' . $maxBytes . ' bytes.');
        }

        if ($ok === false) {
            throw new OutboundException(OutboundException::REASON_TRANSPORT, 'The request failed: ' . $error);
        }

        return new OutboundResponse($status, $response, $received, $url);
    }
}
