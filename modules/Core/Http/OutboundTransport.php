<?php

declare(strict_types=1);

namespace Modules\Core\Http;

/**
 * Sends one HTTPS request to an already validated address (no redirects followed).
 */
interface OutboundTransport
{
    /**
     * @param array<string, string> $headers
     * @param string                $ip      the resolved, checked address to connect to (pinned)
     *
     * @throws OutboundException REASON_TOO_LARGE past $maxBytes, REASON_TRANSPORT on failure
     */
    public function send(string $method, string $url, array $headers, ?string $body, string $ip, int $port, int $connectTimeout, int $timeout, int $maxBytes): OutboundResponse;
}
