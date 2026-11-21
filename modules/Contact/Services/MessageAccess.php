<?php

declare(strict_types=1);

namespace Modules\Contact\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Permissions\Authorization;

/**
 * The record rule of `contact-messages` (D-48-01): a user with `contact-messages.all` reads every
 * message; anyone else only the messages addressed to the recipients whose group they belong to.
 * Used by the back-office CRUD and the dashboard widget, so both show the same messages.
 */
final class MessageAccess
{
    public static function seesAll(User $user): bool
    {
        return Authorization::for($user)->can('contact-messages.all');
    }

    /**
     * Adds the rule to a query on `contact_messages` (nothing for users who see everything).
     *
     * @param BaseBuilder<BaseConnection<object|resource, object|resource>> $builder
     */
    public static function apply(BaseBuilder $builder, User $user): void
    {
        if (self::seesAll($user)) {
            return;
        }

        $ids = (new Recipients())->readableBy($user);
        $builder->whereIn('contact_messages.recipient_id', $ids === [] ? [0] : $ids);
    }
}
