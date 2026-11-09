# Users, groups and the permission matrix

The **Users** module (core module, cannot be disabled) administers accounts, groups and grants
(specification "Modules" › "Users", "RBAC"). It never writes permissions or groups by itself:
accounts go through Shield, groups through the Core's `GroupRegistry`, group membership through
Auth's `GroupRules`, and grants through the Core's `GrantManager`, so every escalation rule of
`EscalationGuard` applies (`docs/rbac.md`).

## Screens and permissions

| Screen | URL | Permission |
|---|---|---|
| Users (kpcrudy) | `backend/users` | `users-users.list` (+ `show`, `add`, `edit`) |
| Row operations | `backend/users/<op>/<masked id>` (POST) | `users-users.<op>` (below) |
| Groups and permissions of a user | `backend/users/access/<id>` | `users-users.access` + `reauth` |
| Groups (kpcrudy) | `backend/users/groups` | `users-groups.*` (changes need a re-authentication) |
| Permission matrix | `backend/users/permissions` | `users-matrix.view`; saving `users-matrix.edit` + `reauth` |

Default grants: `admin` gets every Users permission except `users-users.impersonate` (the
superadmin, who bypasses the checks, is the only one who impersonates). Menu: "Users", "Groups"
and "Permissions" in the `settings` zone. Names and URLs: D-15-01.

## Users

- **List and details:** user name, email (Shield's `email_password` identity), groups, 2FA method,
  status (active, inactive, banned) and last activity; search by user name; filters "active",
  "banned" and a range of last activity. Only columns of `users` can be searched or filtered: the
  email, groups and 2FA live in other tables (kpcrudy request in `docs/library-requests.md`).
- **Add:** user name, email and an initial password (Shield's local password rules). The account
  is created by the CRUD, then `UserAccounts::completeNewAccount()` stores the identity through
  Shield, adds the default group (`AuthGroups.defaultGroup`) and forces a password change at the
  first login.
- **Edit:** user name, email and "active" (nobody deactivates themselves). Administrators never
  set another user's password: they force a password change instead.
- **No deletion** (D-15-03): accounts are banned; Shield's soft-deleted rows are outside the CRUD.
- **Row operations** (`addOperation`, POST with CSRF, masked ids):

  | Key | What it does |
  |---|---|
  | `ban` | Ban/unban; a ban ends every session and remember-me token |
  | `forcereset` | The user must change the password at the next request |
  | `reset2fa` | Turns 2FA off (re-authentication; the user is notified, `TwoFactorReset`) |
  | `logout` | Ends every session and remember-me token of the user |
  | `impersonate` | Superadmins only, re-authentication (below) |
  | `access` | Opens the groups and permissions screen |

- **Who manages whom** (`EscalationGuard::canManageUser()`, D-15-04): superadmins manage every
  account; anybody else only accounts that are not superadmins and hold nothing they do not hold
  themselves (their groups' grants and individual permissions). Every operation but edit is
  refused on one's own account. The buttons are hidden and the server refuses the same.

## Groups and permissions of a user

Checkboxes for the groups and for individual atomic permissions (enabled modules only). What the
current user may not change is disabled, and the server applies the same rules (`UserAccess`):
nobody changes their own access, only a superadmin assigns `superadmin` or touches a
superadmin, a group can only be given by someone holding all its permissions, the last superadmin
keeps the group, individual permissions only if the actor holds them. Saved in one transaction;
the user's sessions are regenerated (`privilegesChanged`).

## Groups

kpcrudy over the Core `groups` table (`GroupRegistry`, D-15-02):

- name (lower case, `[a-z][a-z0-9_-]{1,49}`, set once, never a reserved name), title, description
  and the **2FA policy** (`optional` / `required`; a member of a required group must set up 2FA
  after logging in);
- every change is mirrored into `setting('AuthGroups.groups')` (Shield) with the policy as the
  `twoFactor` key; a new group receives the grants every reserved group holds (profile, sessions,
  my activity, notifications…);
- reserved groups and groups with members cannot be deleted; deleting a group removes its grants;
- only a superadmin edits the `superadmin` group; adding, editing and deleting need a
  re-authentication.

## Permission matrix

One card per module, one row per resource, one column per group (`PermissionMatrix`, D-15-05):

- each cell holds the resource's atomic operations and "All" (the resource wildcard
  `<prefix>.*`); the quick selections "Read" (`list`, `show`, `view`) and "None" only tick
  checkboxes (component `users/matrix`);
- only atomic catalogue names and resource wildcards are accepted; any other posted name refuses
  the whole save; grants the screen does not show (other wildcards, disabled modules) are kept;
- saving completes the grants with their implied permissions (`edit` → `list`, `show`…);
- the columns of the user's own groups and of `superadmin` are read-only (only superadmins
  change them); every group is checked against the escalation rules first and all groups are
  saved in one transaction, then Shield's matrix is rewritten;
- a column that is not on the form is never changed (each editable column posts an empty
  marker).

## Impersonation

See `docs/auth.md` › "Impersonation": superadmins only, never another superadmin, after a
re-authentication, limited by `Users.impersonationMinutes` (30), with a banner and "Back to my
account", sensitive actions refused meanwhile, start and end in the activity log.

## Activity log

CRUD writes are logged by `AuditedCrud`. The module adds `user_created`, `email_changed`,
`user_banned`, `user_unbanned`, `reset_forced`, `logged_out_everywhere`, `access_refused`
(labels `Users.activity.*`); the Core logs grant changes (`grants_changed`,
`permissions_granted`, `permissions_revoked`), Auth group membership, 2FA resets and
impersonation.

## Tests

`vendor/bin/phpunit modules/Users`: `UsersScreensTest` (adding through Shield, password rules,
email changes, bans, forced resets, logout everywhere, 2FA reset with re-authentication,
accounts stronger than the actor, forged POSTs), `ImpersonationTest` (superadmin only, never a
superadmin or a banned user, re-authentication, banner and way back, time limit, sensitive
actions refused, logout), `GroupsAndMatrixTest` (new groups, reserved names, deletion, 2FA
policy, re-authentication, matrix saves atomic names and wildcards with implied permissions,
escalation, a user's access).
