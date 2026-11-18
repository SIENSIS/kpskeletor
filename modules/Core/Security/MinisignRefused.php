<?php

declare(strict_types=1);

namespace Modules\Core\Security;

use RuntimeException;

/**
 * A file refused by `Minisign`; `$reason` is one of the `Minisign::*` constants and `$keyId`
 * the (hexadecimal) id of an unknown key. Callers turn it into their own translated message.
 */
final class MinisignRefused extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly string $keyId = '')
    {
        parent::__construct('Minisign: ' . $reason . ($keyId !== '' ? ' ' . $keyId : ''));
    }
}
