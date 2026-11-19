# Permissions

How the skeleton decides who can do what. Specification: `docs/specification.md`, "RBAC
(centralised, automatic, atomic)"; decisions D-00-17 … D-00-22, D-00-30, D-07-01 … D-07-05 in
`docs/decisions.md`. Code: `modules/Core/Permissions/`. Writing a module's resources step by step:
[`creating-a-module.md`](creating-a-module.md).

## Resources

A module declares its permissions as **resources** in its manifest (`$resources`), one per CRUD
or screen; `ManifestValidator` checks them on `modules:sync`:

```php
public array $resources = [
    'posts' => [
        'label'  => 'News.res.posts',          // language key
        'type'   => 'crud',                    // 'crud' or 'page'
        'except' => ['import'],                // crud only: kpcrudy operations it does not have
        'extra'  => ['publish' => 'News.perm.publish', 'author' => 'News.perm.author'],
    ],
    'read' => ['label' => 'News.res.read', 'type' => 'page'],
];
```

| Key | Meaning |
|---|---|
| resource key | lower case letters and digits (`posts`, `read`) |
| `label` | language key of the resource (shown in the matrix) |
| `type` | `crud`: kpcrudy's operations `list`, `show`, `add`, `edit`, `delete`, `export`, `import`, `trash`, `restore`, `purge`; `page`: `view` |
| `except` | `crud` only: operations to drop (one of the ten above) |
| `extra` | operation => language key: custom operations, bulk actions, sensitive fields (`publish`, `author`, `birthdate`). Write them in lower case (`forcereset`, not `forceReset`): permission checks lowercase the name they look up |
| `name` | permission prefix override (next section) |

Every operation a CRUD or API resource can ask about (its operations, `addOperation()`, bulk
actions, action permissions) must be in the catalogue; outside production `CrudPermissions`
throws `UndeclaredPermissionException` naming the missing ones, and `permissions:check` runs every
CRUD with `CrudAuditor`.

## Names and catalogue

- A permission is `<module>-<resource>.<operation>` in lower case (`news-posts.edit`), or
  `<name>.<operation>` when the resource declares a `'name'` (`modules.toggle`,
  `settings.update`, `backup.create`).
- **Who may use `'name'`:** the Core, and modules with a **single resource** (Api, Backup,
  Dashboard, Files, Pages, Settings, Updates); any other module gets a manifest error. The prefix
  is lower case with hyphens (`^[a-z][a-z0-9]*(-[a-z0-9]+)*$`). `Security` and `Update` are
  reserved module names.
- The **catalogue** is generated from the manifests by `CatalogueBuilder` and stored by
  `php spark modules:sync` in `permissions`: kpcrudy's operations for `type: crud` (minus
  `except`), `view` for `type: page`, plus `extra`, plus `settings.<module>` for modules with
  settings. Duplicate names stop the sync. Never write permissions by hand.
- `search`, `filters` and `bulk` are not permissions: they are part of `list`.
- Permissions of a **disabled** module are hidden: nobody holds them (not even superadmins) until
  the module is enabled again; grants are kept.

## Wildcards and how Shield matches them

Groups may be granted wildcards; Shield's `PermissionMatcher` decides what they match (a user's
individual permissions are atomic only):

| Grant | Matches | Does not match |
|---|---|---|
| `news-posts.edit` | exactly `news-posts.edit` | — |
| `news-posts.*` | every name starting with `news-posts.` (`news-posts.edit`, `news-posts.publish`) | `news-postsx.edit`, `news-posts` |
| `a.*.c` | one segment per `*` (`a.b.c`) | `a.b.d`, `a.b.c.d` |

The text before `.*` is compared literally, dot included, so a wildcard never spills into a
resource whose name merely starts the same. A grant starting with `*` or with `*` inside a
segment (`news-*.edit`, `*.list`) matches nothing. Matching is case-sensitive on lower-case
names (Shield lowercases the requested permission). `GrantManager` refuses a wildcard that
matches no catalogue permission; `ShieldMatrix` leaves out of the matrix the grants that match no
**enabled** permission (they come back when the module is enabled).

## Grants

- **Groups** are data: the Core `groups` table (name, title, description, reserved, 2FA policy),
  mirrored into `setting('AuthGroups.groups')` by `Modules\Core\Groups\GroupRegistry`; the four
  reserved groups (`superadmin`, `admin`, `editor`, `user`) are created by `modules:sync`. Groups
  get grants in `group_permissions` (the source of truth): atomic names or wildcards that match
  some catalogue permission.
- A module's `defaultGrants` (group => atomic names or `<prefix>.*`, only of its own resources)
  are applied once, at its first sync, completed with their implied permissions.
- **Users** can also hold individual atomic permissions (Shield's `addPermission()`, table
  `auth_permissions_users`; no wildcards).
- Change grants only through `GrantManager` (the permission matrix, the Users screens and the
  console):

  ```php
  $grants = new \Modules\Core\Permissions\GrantManager();
  $grants->setGroupGrants($actor, 'editor', ['news-posts.edit', 'news-categories.*']); // atomic save
  $grants->grantToUser($actor, $user, ['news-posts.publish']);
  $grants->revokeFromUser($actor, $user, ['news-posts.publish']);
  ```

  `$actor` is the user making the change (`null`: console or installer). Invalid names or a
  broken escalation rule throw `EscalationException` (translated message) and change nothing.
  Every change is a security-log entry (`grants_changed`, `permissions_granted`,
  `permissions_revoked`); after a group change the matrix is written again and the menu cache is
  cleared; the affected users get `privilegesChanged`.

### Implied permissions

Every saved grant (group or user, and `defaultGrants`) is completed with the permissions it
presupposes (`ImpliedPermissions`, from `Kpcrudy\Crud::impliedPermissions()`, applied
transitively and only for operations the resource has):

| Grant | Also gives |
|---|---|
| `add`, `delete`, `export`, `trash` | `list` |
| `edit` | `list`, `show` |
| `import` | `list`, `add` |
| `restore`, `purge` | `trash` (and so `list`) |

Wildcards and `extra` operations are stored as they are (`news-posts.*` already covers every
operation; `news-posts.publish` does not imply `edit`).

### Personal screens: granted to every group

Screens every signed-in user has (their profile, their sessions, their notifications, their
dashboard, their activity) are still resources with permissions, so every `backend/` route has
`can:` and nothing is reachable by default. Their modules grant them to every reserved group in
`defaultGrants` (Auth: `auth-profile.*`, `auth-sessions.*`; Core: `core-notifications.*`;
Dashboard, Activity, News' reader `news-read.*` likewise), and a **new group** receives the grants
every reserved group holds (`GroupRegistry::commonGrants()`). They can be revoked in the matrix
like any other grant.

## The matrix

- **Shield's matrix.** Shield reads `setting('AuthGroups.permissions')` and
  `setting('AuthGroups.matrix')`. `ShieldMatrix` computes them from the enabled catalogue and
  `group_permissions` and writes them after every `modules:sync`, `modules:enable` /
  `modules:disable` and `GrantManager` change. `Config\AuthGroups` holds only the groups: its
  permissions and matrix are empty on purpose (default deny). `php spark permissions:check` and
  `ShieldMatrixTest` check that the settings and the table agree.
- **The permission matrix screen** (Users module, `backend/users/permissions`, menu
  *Permissions* in the settings zone): groups as columns, the catalogue by module and resource as rows. Viewing needs
  `users-matrix.view`; saving needs `users-matrix.edit` and re-authentication, saves each group
  atomically through `GrantManager` and only lets a user change the groups and permissions the
  escalation rules allow ([`users.md`](users.md)). A user's groups and individual permissions
  are edited in *Groups and permissions* (`backend/users/access/<id>`, `users-users.access` +
  re-authentication).

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

### Own records (record rules)

A permission says what a user may do with a resource; **record rules** say on which rows. They
are kpcrudy/kpapy rules on top of `CrudPermissions`, enforced on the server for every action
(list, details, forms, exports, bulk actions, forged POSTs):

- **See only own records** ("My posts", `news-mine`): `where('author_id', (int) auth()->id())`.
  Other rows do not exist for that CRUD: a forged id answers like a missing record.
- **See all, change only own** (`news-posts` without `news-posts.author`):

  ```php
  $own = static fn (?array $row): bool => $row === null || (int) ($row['author_id'] ?? 0) === $me;
  foreach (['edit', 'delete', 'publish'] as $operation) {
      $crud->setPermission($operation, $own);   // only narrows what the permission allows
  }
  ```

- **API resources** use the same idea with kpapy's `where(Closure)` (News: `NewsVisibility::apply()`,
  so an unreadable post is 404).
- Outside a request (feeds, tasks, notifications), resolve for the right user with
  `CrudPermissions::forUser($user)` / `Authorization::for($user)`, never the session.

Masked ids are not access control; tests prove each rule with forged requests
(`PostsCrudTest::testAnEditorChangesOnlyTheirOwnPosts`, `testMyPostsOnlyShowsTheUsersPostsEvenWithAForgedId`).

### Hidden fields

A sensitive field gets its own `extra` permission and is removed for users who lack it, in the
form, the details, the list, the export and against forged posts:

| Permission | Field | How |
|---|---|---|
| `news-posts.author` | `author_id` of posts | `if (! $this->can('news-posts.author')) { $crud->hideFields(['author_id']); }` (`modules/News/Controllers/Posts.php`) |
| `tasks-tasks.estimates` | `estimate_hours` | `hideFields()` in the CRUD and, with `Authorization::for($user)`, in the kpapy resource (`modules/Tasks/Controllers/Api/Tasks.php`) |
| `users-users.birthdate` | the user's date of birth | the field is only added to `showFields()` with the permission (`modules/Users/Controllers/Users.php`); the same permission guards the administrative birthdays calendar |

Grant the permission in the matrix to show the field to a group.

## Routes: default deny

- The `backend` filter chain runs on the back-office prefix and everything under it (`backend`
  and `backend/*` by default; `app.backendPath`, D-40-01, set in the constructor of
  `app/Config/Filters.php`): login
  (Shield `session`), forced password change, forced 2FA set-up, the per-user request limit
  (`RateLimit`, limit `backend`, `docs/security.md`). The global filters cover the
  disabled-module check (`moduleenabled`) and the session hardening with the idle lock
  (`authsession`), and maintenance mode (`maintenance`, first). After the rate limit the chain
  runs the scheduler fallback (`PoorMansCron`, off unless `Core.poorMansCron`).
- Every back-office route (`BackendPath::contains()`) also has its own `can:` filter; sensitive ones add `reauth`.
- Every `api/v1/` route has `kpapy`, `kpapy:optional` or `kpapy:hmac`; `kpapy:login` only on the
  `auth/token*` login routes; `kpapy:public` only for GET. No other route may live under `api/`
  (it has no CSRF or session filters): the auditor refuses `api/internal/…` or `api/v2/…`.
- The sensitive routes that use the `reauth` filter are listed in
  `RoutesSecurityTest::testSensitiveRoutesRequireReauthentication`: add a new one there.
- `tests/feature/Security/RoutesSecurityTest.php` walks every route with `RouteSecurityAuditor`
  and fails on any breach (also on a `can:` naming a permission outside the catalogue);
  `php spark permissions:check` reports the same.
- kpcrudy CRUDs (`docs/cruds.md`): `CrudFactory::routes()` adds `backend` and
  `can:<resource>.list`; each operation is then `<resource>.<operation>` through `CrudPermissions`
  (`Config\Kpcrudy`, deny by default). `permissions:check` also runs every CRUD action with
  `CrudAuditor` and fails when a CRUD is not made with the factory or asks about an operation its
  manifest does not declare.

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

- `php spark permissions:list [--module=<Name>] [--group=<group>]`: the catalogue (name,
  module, description) and which groups hold each permission.
- `php spark permissions:check`: errors for invalid manifests or duplicate names, generated
  permissions missing from the stored catalogue, Shield's matrix out of date, route filters
  (`RouteSecurityAuditor`), `$api` entries without their route, CRUDs not made with
  `CrudFactory` or asking about undeclared operations (`CrudAuditor`) and the same for kpapy
  resources (`ApiAuditor`); warnings for stored permissions no manifest generates, grants naming
  unknown permissions and atomic grants without their implied permissions. It exits with an
  error on any error (CI).

Options and output: [`commands.md`](commands.md).
