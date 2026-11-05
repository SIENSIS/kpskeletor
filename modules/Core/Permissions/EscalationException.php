<?php

declare(strict_types=1);

namespace Modules\Core\Permissions;

use RuntimeException;

/**
 * A change of groups or permissions refused by the escalation rules or the grant validation.
 * The message is a translated text, safe to show to the user.
 */
final class EscalationException extends RuntimeException
{
}
