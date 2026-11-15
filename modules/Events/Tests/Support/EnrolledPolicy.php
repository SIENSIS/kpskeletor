<?php

declare(strict_types=1);

namespace Modules\Events\Tests\Support;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Calendar\EventAccessPolicy;

/**
 * A test access policy: only the users listed per key may see an event ("enrolled" users).
 */
final class EnrolledPolicy implements EventAccessPolicy
{
    /** @var array<string, list<int>> key => user ids */
    public static array $enrolled = [];

    public function canSee(User $user, string $key): bool
    {
        return in_array((int) $user->id, self::$enrolled[$key] ?? [], true);
    }
}
