<?php

declare(strict_types=1);

namespace Modules\Devtools\Packages;

/**
 * A package could not be built (no such module, a symbolic link, the installer's validation
 * refused it…); the message is translated and safe to show.
 */
final class PackageBuildFailed extends \RuntimeException
{
}
