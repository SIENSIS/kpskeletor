<?php

declare(strict_types=1);

namespace Modules\Core\Archive;

use RuntimeException;

/**
 * A ZIP archive (backup or module package) was refused by SafeZip before anything was extracted
 * (zip-slip, zip bomb, too many entries, an entry outside the allowed folders…). The message is a
 * translated text safe to show to the user.
 */
final class ArchiveRefused extends RuntimeException
{
}
