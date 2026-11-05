<?php

declare(strict_types=1);

use Modules\Core\Permissions\Authorization;

if (! function_exists('can')) {
    /**
     * Whether the logged-in user holds one of the permissions (the central `Authorization`:
     * guests hold nothing, superadmins every permission of the enabled modules). For views and
     * controllers; the server must also enforce what the UI hides.
     *
     * ```php
     * <?php if (can('news-posts.publish')): ?> … <?php endif ?>
     * ```
     */
    function can(string ...$permissions): bool
    {
        return Authorization::current()->can(...$permissions);
    }
}

if (! function_exists('can_all')) {
    /**
     * Whether the logged-in user holds every one of the permissions.
     */
    function can_all(string ...$permissions): bool
    {
        return Authorization::current()->canAll(...$permissions);
    }
}
