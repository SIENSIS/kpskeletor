<?php

declare(strict_types=1);

namespace Modules\Devtools\Packages;

/**
 * Signing a package failed. The message is a reason code (`format`, `password`,
 * `passwordRequired`, `verify`, `package`, `failed`) translated with
 * `Devtools.packages.sign.errors.<reason>`; it never carries the key or the password.
 */
final class SigningFailed extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }

    public function userMessage(): string
    {
        $key = 'Devtools.packages.sign.errors.' . $this->reason;
        $text = lang($key);

        return $text === $key ? lang('Devtools.packages.sign.errors.failed') : $text;
    }
}
