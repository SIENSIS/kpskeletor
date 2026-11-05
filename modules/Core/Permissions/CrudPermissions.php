<?php

declare(strict_types=1);

namespace Modules\Core\Permissions;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Kpapy\Auth\Context;
use Kpapy\PermissionResolver as ApiResolver;
use Kpapy\Resource;
use Kpcrudy\Crud;
use Kpcrudy\PermissionResolver as CrudResolver;

/**
 * The one permission resolver of every kpcrudy CRUD and every kpapy resource
 * (`Config\Kpcrudy::$permissionResolver`, `Config\Kpapy::$permissionResolver`, both with
 * `$denyByDefault = true`). The last parameter is typed `object` so one class implements both
 * interfaces.
 *
 * - `(resource, operation)` → `Authorization::for($user)->can("{resource}.{operation}")`;
 *   `search`, `filters` and `bulk` are part of `list`; superadmins pass (Authorization).
 * - The user: the one given to `forUser()` (iCal feeds, notifications, tasks, CLI), otherwise
 *   kpapy's `Context` for API resources and the session for CRUDs. No user: denied.
 * - Outside production, the first question about a resource compares the operations the CRUD
 *   or resource can ask about (`permissionCatalogue()`) with the catalogue generated from the
 *   manifests and throws `UndeclaredPermissionException` naming the missing ones.
 * - Record rules (`setPermission()`, `where()`) still restrict what this allows.
 */
final class CrudPermissions implements CrudResolver, ApiResolver
{
    /** Operations that are part of `list`. */
    public const LIST_OPERATIONS = ['search', 'filters', 'bulk'];

    /** @var array<string, true> Resources already compared with the catalogue in this process */
    private static array $checked = [];

    private ?User $user = null;

    private bool $explicitUser = false;

    public function __construct(private readonly PermissionCatalogue $catalogue = new PermissionCatalogue())
    {
    }

    /**
     * A resolver for an explicit user, never the session (null: a guest).
     */
    public static function forUser(?User $user, ?PermissionCatalogue $catalogue = null): self
    {
        $resolver               = new self($catalogue ?? new PermissionCatalogue());
        $resolver->user         = $user;
        $resolver->explicitUser = true;

        return $resolver;
    }

    /**
     * @param array<string, mixed>|null $row
     * @param Crud|Resource             $crud
     */
    public function allows(string $resource, string $operation, ?array $row, object $crud): bool
    {
        if (ENVIRONMENT !== 'production') {
            $this->assertDeclared($resource, $crud);
        }

        return Authorization::for($this->userFor($crud), $this->catalogue)->can(self::permission($resource, $operation));
    }

    /**
     * The permission name of an operation (`search`/`filters`/`bulk` → `list`).
     */
    public static function permission(string $resource, string $operation): string
    {
        return $resource . '.' . (in_array($operation, self::LIST_OPERATIONS, true) ? 'list' : $operation);
    }

    /**
     * Operations a CRUD or API resource can ask about that the catalogue does not declare.
     *
     * @param Crud|Resource $crud
     *
     * @return list<string>
     */
    public function undeclared(string $resource, object $crud): array
    {
        $declared = [];

        foreach (array_keys($this->catalogue->stored()) as $name) {
            if (str_starts_with($name, $resource . '.')) {
                $declared[] = substr($name, strlen($resource) + 1);
            }
        }

        $asked = array_diff($crud->permissionCatalogue(), self::LIST_OPERATIONS);

        if (array_intersect(['search', 'filters', 'bulk'], $crud->permissionCatalogue()) !== []) {
            $asked[] = 'list';
        }

        $missing = array_values(array_unique(array_diff($asked, $declared)));
        sort($missing);

        return $missing;
    }

    /**
     * Forgets which resources were compared with the catalogue (tests).
     */
    public static function reset(): void
    {
        self::$checked = [];
    }

    /**
     * @param Crud|Resource $crud
     */
    private function assertDeclared(string $resource, object $crud): void
    {
        if (isset(self::$checked[$resource])) {
            return;
        }

        $missing = $this->undeclared($resource, $crud);

        if ($missing !== []) {
            throw UndeclaredPermissionException::for($resource, $missing);
        }

        self::$checked[$resource] = true;
    }

    private function userFor(object $crud): ?User
    {
        if ($this->explicitUser) {
            return $this->user;
        }

        if ($crud instanceof Resource) {
            $user = Context::user();

            if ($user === null || $user instanceof User) {
                return $user;
            }

            $id = $user->id ?? null;

            return is_numeric($id) ? model(UserModel::class)->findById((int) $id) : null;
        }

        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
