<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Modules\Core\Http\OutboundException;
use Modules\Core\Http\OutboundResponse;
use Modules\Core\Http\OutboundTransport;

/**
 * A push service for the tests: answers every request with the next queued status (201 by
 * default) and records the requests (no network).
 */
final class FakePushTransport implements OutboundTransport
{
    /** @var list<int|OutboundException> */
    public array $answers = [];

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: string|null, ip: string}> */
    public array $sent = [];

    public function send(string $method, string $url, array $headers, ?string $body, string $ip, int $port, int $connectTimeout, int $timeout, int $maxBytes): OutboundResponse
    {
        $this->sent[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body, 'ip' => $ip];
        $answer       = array_shift($this->answers) ?? 201;

        if ($answer instanceof OutboundException) {
            throw $answer;
        }

        return new OutboundResponse($answer, [], '', $url);
    }
}
