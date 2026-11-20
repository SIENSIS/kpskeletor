<?php

declare(strict_types=1);

namespace Modules\Core\Generator;

/**
 * The generator cannot build or write a plan (an existing file it cannot change, a file changed
 * since the preview, a write that failed). The message is translated and safe to show.
 */
final class GeneratorException extends \RuntimeException
{
}
