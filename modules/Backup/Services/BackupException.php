<?php

declare(strict_types=1);

namespace Modules\Backup\Services;

use RuntimeException;

/**
 * A backup could not be created, verified or restored. The message is translated and safe to
 * show (never a path or a secret).
 */
final class BackupException extends RuntimeException
{
}
