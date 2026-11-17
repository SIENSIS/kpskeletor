<?php

declare(strict_types=1);

namespace Modules\Core\Packages;

use RuntimeException;

/**
 * A module or theme package that cannot be installed (validation, dependencies, versions,
 * signature) or an install/uninstall that failed. The message is meant for the operator.
 */
final class PackageRefused extends RuntimeException
{
}
