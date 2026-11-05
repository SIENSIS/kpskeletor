<?php

declare(strict_types=1);

namespace Modules\Core\Http;

use JsonException;

/**
 * The answer of an outbound request.
 */
final class OutboundResponse
{
    /**
     * @param array<string, string> $headers lower-case name => value (the last one wins)
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
        public readonly string $url = '',
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function isRedirect(): bool
    {
        return in_array($this->status, [301, 302, 303, 307, 308], true) && $this->header('location') !== null;
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * @throws JsonException
     */
    public function json(): mixed
    {
        return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
    }
}
