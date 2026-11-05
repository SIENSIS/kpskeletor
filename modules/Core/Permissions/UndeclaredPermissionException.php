<?php

declare(strict_types=1);

namespace Modules\Core\Permissions;

use LogicException;

/**
 * A CRUD or API resource asks about operations its module's manifest does not declare
 * (thrown outside production only, by `CrudPermissions`).
 */
final class UndeclaredPermissionException extends LogicException
{
    /**
     * @param list<string> $missing
     */
    public static function for(string $resource, array $missing): self
    {
        return new self(sprintf(
            'The resource "%s" asks about operations that its manifest does not declare: %s. Add them to the resource\'s "extra" (or remove them from "except") and run `php spark modules:sync`.',
            $resource,
            implode(', ', $missing),
        ));
    }
}
