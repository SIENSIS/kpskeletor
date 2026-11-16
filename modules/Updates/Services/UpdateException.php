<?php

declare(strict_types=1);

namespace Modules\Updates\Services;

use RuntimeException;

/**
 * An update check or an update failed; the message is translated and safe to show.
 */
final class UpdateException extends RuntimeException
{
}
