<?php

declare(strict_types=1);

namespace Modules\Auth\Exceptions;

use RuntimeException;

/**
 * A group change refused by the reserved-group rules (GroupRules).
 */
final class GroupRuleException extends RuntimeException
{
}
