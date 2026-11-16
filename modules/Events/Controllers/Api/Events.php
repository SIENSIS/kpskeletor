<?php

declare(strict_types=1);

namespace Modules\Events\Controllers\Api;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Kpapy\Auth\Context;
use Modules\Events\Services\EventsApi;

/**
 * API resource `events` (`api/v1/events`, kpapy, read-only, D-26-01): the events the token's user
 * sees, with the calendar's visibility rules (`EventVisibility`: public, private, `restricted`
 * audiences, calendar permissions and access policies). Permissions `events-api.list` /
 * `events-api.show`, granted to every group; the token's scopes still narrow them. Operations
 * `occurrences` (range, calendars, providers) and `calendars`: `Services/EventsApi.php`.
 */
final class Events extends Controller
{
    public const PATH = 'api/v1/events';

    public function index(): ResponseInterface
    {
        return EventsApi::resource(self::user(), false, self::PATH)->render();
    }

    private static function user(): ?User
    {
        $user = Context::user();

        if ($user === null || $user instanceof User) {
            return $user;
        }

        $id = $user->id ?? null;

        return is_numeric($id) ? model(UserModel::class)->findById((int) $id) : null;
    }
}
