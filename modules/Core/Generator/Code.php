<?php

declare(strict_types=1);

namespace Modules\Core\Generator;

/**
 * Raw PHP code inside a value given to `PhpExport` (e.g. `ContentTranslations::locales()`).
 */
final class Code
{
    public function __construct(public readonly string $code)
    {
    }
}
