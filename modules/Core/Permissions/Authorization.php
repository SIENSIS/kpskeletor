<?php

declare(strict_types=1);

namespace Modules\Core\Permissions;

use CodeIgniter\Shield\Entities\User;

/**
 * Permission checks for an explicit user (docs/specification.md, "RBAC"; decision D-00-20):
 * the web session, kpapy's `Context`, iCal feeds, notifications, scheduled tasks and the CLI all
 * ask through here, never through the session alone.
 *
 * ```php
 * Authorization::for($user)->can('news-posts.edit');              // any of them
 * Authorization::for($user)->canAll('news-posts.edit', 'news-posts.publish');
 * Authorization::current()->can('settings.update');               // logged-in user (or guest)
 * ```
 *
 * Rules: guests hold nothing; a permission that is not in the catalogue of the enabled modules
 * is refused to everybody (typos and disabled modules fail closed, decision D-07-03);
 * superadmins hold every other permission; everybody else through Shield (the group matrix
 * written by `ShieldMatrix` and the user's individual permissions).
 */
final class Authorization
{
    public const SUPERADMIN = 'superadmin';

    private function __construct(
        private readonly ?User $user,
        private readonly PermissionCatalogue $catalogue,
    ) {
    }

    public static function for(?User $user, ?PermissionCatalogue $catalogue = null): self
    {
        return new self($user, $catalogue ?? new PermissionCatalogue());
    }

    /**
     * The logged-in user of the current request (null for guests).
     */
    public static function current(): self
    {
        $user = auth()->user();

        return self::for($user instanceof User ? $user : null);
    }

    public static function isSuperadmin(?User $user): bool
    {
        return $user !== null && $user->inGroup(self::SUPERADMIN);
    }

    public function user(): ?User
    {
        return $this->user;
    }

    /**
     * True when the user holds at least one of the permissions.
     */
    public function can(string ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->holds($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the user holds every permission (false for an empty list).
     */
    public function canAll(string ...$permissions): bool
    {
        if ($permissions === []) {
            return false;
        }

        foreach ($permissions as $permission) {
            if (! $this->holds($permission)) {
                return false;
            }
        }

        return true;
    }

    private function holds(string $permission): bool
    {
        if ($this->user === null) {
            return false;
        }

        $permission = strtolower($permission);

        if (! in_array($permission, $this->catalogue->enabledNames(), true)) {
            return false;
        }

        return self::isSuperadmin($this->user) || $this->user->can($permission);
    }
}
