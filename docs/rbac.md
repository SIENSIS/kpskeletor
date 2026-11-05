# Permissions (RBAC)

How the skeleton decides who can do what. Specification: `docs/specification.md`, "RBAC
(centralised, automatic, atomic)"; decisions D-00-17 … D-00-22, D-00-30, D-07-01 … D-07-05 in
`docs/decisions.md`. Code: `modules/Core/Permissions/`.

## Names and catalogue

- A permission is `<module>-<resource>.<operation>` in lower case (`news-posts.edit`), or
  `<name>.<operation>` when a Core or single-resource module declares the resource's `'name'`
  (`modules.toggle`, `settings.update`).
- The **catalogue** is generated from the manifests by `CatalogueBuilder` and stored by
  `php spark modules:sync` in `permissions`: kpcrudy's operations for `type: crud` (minus
  `except`), `view` for `type: page`, plus `extra`, plus `settings.<module>` for modules with
  settings. Duplicate names stop the sync. Never write permissions by hand.
- `search`, `filters` and `bulk` are not permissions: they are part of `list`.
- Permissions of a **disabled** module are hidden: nobody holds them (not even superadmins) until
  the module is enabled again; grants are kept.

## Grants

- **Groups** (`superadmin`, `admin`, `editor`, `user`; data in session 15) get grants in
  `group_permissions` (the source of truth): atomic names or Shield wildcards that match some
  catalogue permission (`news-posts.*` grants a whole resource and never matches
  `news-postsx.edit`).
- A module's `defaultGrants` are applied once, at its first sync. Personal screens
  (`auth-profile`, `auth-sessions`, later `activity-mine`, `core-notifications`, `dashboard`) are
  resources granted to every group this way, so every `backend/` route has `can:`.
- Every grant is completed with the permissions it presupposes (`ImpliedPermissions`, from
  `Kpcrudy\Crud::impliedPermissions()`: `edit` → `list`, `show`; `purge` → `trash`; `export` →
  `list`…).
- **Users** can also hold individual atomic permissions (Shield's `addPermission()`, table
  `auth_permissions_users`).
- Change grants only through `GrantManager` (the matrix screen of session 15 and the console):

  ```php
  $grants = new \Modules\Core\Permissions\GrantManager();
  $grants->setGroupGrants($actor, 'editor', ['news-posts.edit', 'news-categories.*']); // atomic save
  $grants->grantToUser($actor, $user, ['news-posts.publish']);
  $grants->revokeFromUser($actor, $user, ['news-posts.publish']);
  ```

  `$actor` is the user making the change (`null`: console or installer). Invalid names or a
  broken escalation rule throw `EscalationException` (translated message) and change nothing.

## Shield's matrix

Shield reads `setting('AuthGroups.permissions')` and `setting('AuthGroups.matrix')`.
`ShieldMatrix` computes them from the enabled catalogue and `group_permissions` and writes them
after every `modules:sync`, `modules:enable`/`modules:disable` and `GrantManager` change.
`Config\AuthGroups` holds only the groups: its permissions and matrix are empty on purpose
(default deny). `php spark permissions:check` and `ShieldMatrixTest` check that the settings and
the table agree.

## Checking permissions

| Where | How |
|---|---|
| Routes | `'filter' => 'can:news-posts.list'` (any of several: `can:a.x,b.y`; all of them: `can:all,a.x,b.y`) |
| Controllers | `$this->can('news-posts.publish')` (BackendController) |
| Views | `can('news-posts.publish')`, `can_all(…)` (helper `can`, autoloaded) |
| Any user (feeds, tasks, CLI, notifications) | `Authorization::for($user)->can(…)` / `->canAll(…)` |
| CRUDs and API resources | `CrudPermissions` (resolver of kpcrudy and kpapy), `$crud->isAllowed('publish', $row)` |

`Authorization` is the only decision point: guests hold nothing; a name outside the enabled
catalogue is refused to everybody; superadmins hold everything else; other users through Shield
(group matrix + individual permissions). The UI only hides what the server also enforces.

### `CrudPermissions`

One class implements `Kpcrudy\PermissionResolver` and `Kpapy\PermissionResolver` (last
parameter typed `object`). It is configured in `app/Config/Kpcrudy.php` and `app/Config/Kpapy.php`
with `$denyByDefault = true` (sessions 09 and 10). It maps `(resource, operation)` to
`Authorization::for($user)->can("{resource}.{operation}")`; the user is kpapy's `Context` user
for API resources, the session user for CRUDs, or the one given to
`CrudPermissions::forUser($user)` (never the session). Record rules (`setPermission()`, `where()`)
can still restrict what it allows.

Outside production, the first question about a resource compares the operations the CRUD can ask
about (`permissionCatalogue()`, custom operations and action permissions included) with the
catalogue and throws `UndeclaredPermissionException` naming the missing ones: declare them in the
manifest (`extra`) and run `modules:sync`.

### Own records and sensitive fields

- See only own records: kpcrudy `where('author_id', $userId)`.
- See all, change only own: `setPermission('edit', fn (?array $row) => $row === null || …)`.
- Sensitive fields: an `extra` permission (`news-posts.author`) and `hideFields([...])` when the
  user lacks it.

## Routes: default deny

- The `backend` filter chain runs on `backend` and `backend/*` (`app/Config/Filters.php`): login
  (Shield `session`), forced password change, forced 2FA set-up. The global filters cover the
  disabled-module check (`moduleenabled`) and the session hardening with the idle lock
  (`authsession`); throttling, maintenance mode and the activity hook join the chain in the
  sessions that build them (08, 28, 12).
- Every `backend/` route also has its own `can:` filter; sensitive ones add `reauth`.
- Every `api/v1/` route has `kpapy`, `kpapy:optional` or `kpapy:hmac`; `kpapy:login` only on the
  `auth/token*` login routes; `kpapy:public` only for GET.
- `tests/feature/Security/RoutesSecurityTest.php` walks every route with `RouteSecurityAuditor`
  and fails on any breach (also on a `can:` naming a permission outside the catalogue);
  `php spark permissions:check` reports the same.

## Escalation rules (`EscalationGuard`)

- Nobody changes their own groups or individual permissions.
- A user only grants permissions they hold (a wildcard: every permission it matches), only adds
  someone to a group whose permissions they hold, and never changes the grants of a group they
  belong to.
- Only a superadmin touches the `superadmin` group and a superadmin's groups or permissions.
- The reserved groups (`superadmin`, `admin`, `editor`, `user`) cannot be renamed, deleted or
  created again; the last superadmin cannot lose the group.
- After a change, the affected users get the `privilegesChanged` event: Auth regenerates their
  session ids (`SessionTracker::privilegesChanged()`).

## Commands

- `php spark permissions:list [--module=<Name>] [--group=<group>]`
- `php spark permissions:check`

See `docs/commands.md`.
