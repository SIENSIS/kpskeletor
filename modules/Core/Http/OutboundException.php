<?php

declare(strict_types=1);

namespace Modules\Core\Http;

use RuntimeException;

/**
 * An outbound request refused or failed. `reason` is one of the REASON_* constants.
 */
final class OutboundException extends RuntimeException
{
    public const REASON_URL       = 'url';
    public const REASON_SCHEME    = 'scheme';
    public const REASON_PORT      = 'port';
    public const REASON_HOST      = 'host';
    public const REASON_DNS       = 'dns';
    public const REASON_PRIVATE   = 'private';
    public const REASON_REDIRECTS = 'redirects';
    public const REASON_TOO_LARGE = 'tooLarge';
    public const REASON_TRANSPORT = 'transport';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
