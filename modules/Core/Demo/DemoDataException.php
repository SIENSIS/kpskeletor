<?php

declare(strict_types=1);

namespace Modules\Core\Demo;

use RuntimeException;

/**
 * Demo data cannot be loaded or removed; the message is translated.
 */
final class DemoDataException extends RuntimeException
{
}
