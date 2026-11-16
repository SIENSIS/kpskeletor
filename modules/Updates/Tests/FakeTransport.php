<?php

declare(strict_types=1);

namespace Modules\Updates\Tests;

use Modules\Core\Http\OutboundException;
use Modules\Core\Http\OutboundResponse;
use Modules\Core\Http\OutboundTransport;

/**
 * Answers outbound requests by URL (no network in tests) and records them.
 */
final class FakeTransport implements OutboundTransport
{
    /** @var array<string, OutboundResponse|OutboundException> URL => answer */
    public array $routes = [];

    /** @var list<array{method: string, url: string, headers: array<string, string>, maxBytes: int}> */
    public array $sent = [];

    public function send(string $method, string $url, array $headers, ?string $body, string $ip, int $port, int $connectTimeout, int $timeout, int $maxBytes): OutboundResponse
    {
        $this->sent[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'maxBytes' => $maxBytes];
        $answer       = $this->routes[$url] ?? new OutboundResponse(404, [], 'not found');

        if ($answer instanceof OutboundException) {
            throw $answer;
        }

        if (strlen($answer->body) > $maxBytes) {
            throw new OutboundException(OutboundException::REASON_TOO_LARGE, 'Too large.');
        }

        return $answer;
    }
}
