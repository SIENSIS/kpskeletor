# Authentication and sessions

The **Auth** module (`modules/Auth/`, a core module that cannot be disabled) adds login, magic
links, password reset, hardened sessions, two-factor authentication, re-authentication ("sudo
mode"), the profile and "my sessions" on top of Shield.

## Screens and routes

| URL | What |
|---|---|
| `GET/POST login` | Password login (optional "remember me" and captcha) |
| `GET/POST logout` | GET asks for confirmation; the logout is a POST with the CSRF token |
| `GET/POST login/magic-link`, `GET login/verify-magic-link?token=` | Single-use login link by email |
| `GET/POST reset-password`, `GET/POST reset-password/new?token=` | Reset link by email, then a new password |
| `GET auth/captcha` | Captcha image (404 while `Auth.captcha` is off) |
| `GET/POST auth/lock` | Idle lock screen (`Auth.idleTimeout`) |
| `backend/profile` (`auth-profile.view`/`.edit`) | Name, email (needs the current password), language, time zone, colour mode, birth date |
| `backend/profile/password` (`auth-profile.view`/`.edit`) | Password change; the only back-office page while a reset is forced |
| `backend/profile/sessions` (`auth-sessions.view`/`.revoke`) | Open sessions with remote logout, API tokens with revocation |
| `GET auth/a/show`, `POST auth/a/verify`, `auth/a/handle`, `auth/a/cancel` | 2FA step of a pending login (code, recovery code, new email code, cancel); 404 without a pending login |
| `GET/POST auth/reauth`, `POST auth/reauth/send-code` | Re-authentication: password and, with 2FA, a code |
| `backend/profile/two-factor` (`auth-profile.view`) | Method (none / email / app), recovery codes left |
| `POST backend/profile/two-factor/email`, `GET/POST …/app`, `POST …/disable`, `POST …/recovery-codes` (`auth-profile.edit` + `reauth`) | Enable email codes, enrol an authenticator app (QR + key + code), turn off, new recovery codes |

`auth-profile` and `auth-sessions` are granted to every group. `backend/*` also runs Shield's
`session` filter (login required), `forcereset` and `force2fa`.

## Rules

- **No enumeration:** a failed login always says "the email address or the password is not
  correct" (an unknown email costs a password hash too); magic-link and reset requests always
  answer "if an account exists we have sent an email". Only someone who knows the password learns
  that an account is banned.
- **Rate limits** (cache counters): failed logins per IP and per account within
  `Auth.loginLockMinutes` → 429 with `Retry-After`; email links per IP and hour → 429, per account
  and hour → no more emails (silently). Wrong current passwords and lock-screen attempts count as
  failed logins.
- **Captcha** (`Auth.captcha`): an arithmetic image drawn with GD; only an HMAC of the answer is
  in the session; checked once with `hash_equals()`; enforced in every environment.
- **Passwords:** Shield's local validators (composition, dictionary, personal information; never
  the "pwned" API), minimum `Auth.minimumPasswordLength` (8, 12 recommended), copied into
  `config('Auth')` on every request by `AuthPolicy::apply()`.
- **Email links:** tokens of 48 hex characters, stored as SHA-256, single use, valid
  `Auth.magicLinkLifetime` (1 hour). Security emails are sent inline with a 5-second SMTP timeout
  and logged (kind and user id).
- **Sessions:** cookies HttpOnly, SameSite=Lax, Secure in production; the session id is
  regenerated on login, logout, unlock, password change and privilege change (`GroupRules`,
  `SessionTracker::privilegesChanged()`); old ids are destroyed.
  - absolute lifetime `Auth.absoluteLifetime` (minutes) from the login, then the session ends;
  - remember-me may re-create it, but never after `Auth.rememberLifetime` days from the
    remember-me login (0 turns remember-me off);
  - idle lock `Auth.idleTimeout` (minutes, 0 = off);
  - every session is a row of `user_sessions`; logging one out from "my sessions" also deletes its
    remember-me token.
- **Password change or reset:** ends every other session (and their remember-me tokens), revokes
  the access tokens obtained by app logins (personal tokens, marked `secret2 = 'personal'`, stay)
  and clears a forced reset. Event `passwordChanged`.
- **New device:** a random `kp_device` cookie (hash per user in `known_devices`); a login from an
  unknown device emails a notice with a link to "my sessions" (not for the first device).
- **Groups:** `superadmin`, `admin`, `editor`, `user` (`Config\AuthGroups`; their permissions
  come from `group_permissions`, see `docs/rbac.md`). Superadmins bypass `can:`; only a superadmin
  assigns or removes `superadmin` or changes a superadmin's groups, the last one keeps it, nobody
  changes their own groups, nobody adds a user to a group holding permissions they lack, and the
  four groups cannot be renamed or deleted (`GroupRules` → Core `EscalationGuard`).

## Two-factor authentication

- **Methods**, chosen by each user in the profile: none, **email** (a 6-digit code sent at every
  login) or **authenticator app** (TOTP, RFC 6238: 6 digits, 30 s, SHA-1; Google/Microsoft
  Authenticator, Aegis, 1Password…). Table `user_two_factor` (one row per user with 2FA).
- **Login step:** Shield's single `login` action is `Modules\Auth\Authentication\UserTwoFactorAction`,
  which applies only to users with 2FA (password and magic-link logins). Email codes are hashed,
  valid 10 minutes, single use, sent inline by `SecurityMailer`; "send a new code" follows the
  email limits. Wrong codes count as failed logins; past the limit the pending login ends.
- **App enrolment:** a 160-bit secret kept in the session, its QR code (local SVG, `data:` image on
  white) and the key in text; it is saved, encrypted with CI4 Encryption, only after a valid code.
  Needs `encryption.key` in `.env` (the method is unavailable without it).
- **Codes:** the current 30-second step ±1 is accepted, and never a step already used (the last
  accepted step is stored).
- **Recovery codes:** 10 codes `XXXXX-XXXXX` (case and dash ignored), SHA-256 in
  `user_recovery_codes`, single use, shown once, regenerable; created when a method is enabled,
  deleted when 2FA is turned off.
- **Remember-me:** for a user with 2FA the token is issued only after the code
  (`SessionAuthenticator`); a remember-me login does not ask the code again (trusted device)
  but sensitive actions do.
- **Group policy** (column `two_factor` of the Core `groups` table, edited in Users › Groups and
  mirrored as each group's `twoFactor` key of `setting('AuthGroups.groups')`; read with
  `AuthPolicy::twoFactorPolicy()`): a user of a required group without 2FA is sent to the set-up screen by `force2fa`
  and cannot turn it off.
- **Admin reset:** `(new TwoFactor())->reset($user, $adminId)` turns it off, deletes the recovery
  codes and a pending code, logs it and fires `twoFactorReset` (the user gets the mandatory notification `TwoFactorReset`, `docs/notifications.md`; screen:
  the `reset2fa` row operation of Users, with re-authentication). Enabling/disabling fires `twoFactorChanged`.

## Re-authentication ("sudo mode")

- Sensitive actions ask again for the password and, with 2FA, a code (or a recovery code);
  the confirmation lasts `Auth.reauthMinutes` (15) for the PHP session.
- A password login (with its 2FA step) opens the window; a magic link, a session re-created by
  remember-me and the idle lock do not (decision D-06-04).
- Wrong answers count as failed logins; past the limit the session ends.
- A GET is sent to `auth/reauth` and back afterwards; a POST is never replayed: the user confirms
  and repeats the action. JSON requests get 401 `{"error": …, "reauth": url}`.
- While impersonating, re-authentication is never possible: the `reauth` filter answers 403 and
  `auth/reauth` sends back to the profile.

## Impersonation

`Modules\Auth\Services\Impersonation` (started by the Users module's `impersonate` row
operation, `docs/users.md`): a superadmin acts as another user inside their own PHP session.

- Only superadmins, never as another superadmin, a banned or inactive user or themselves, never
  twice; the starting operation needs a re-authentication. The time limit is
  `Users.impersonationMinutes` (30, 1–120; Settings › Security).
- The session keeps the impersonator's tracked row (`user_sessions`: absolute lifetime and remote
  logout still apply); the session id is regenerated and the re-authentication window forgotten
  at the start and at the end.
- The global `authsession` filter shows the banner in the back office's top bar (who, until when,
  "Back to my account" → `POST auth/impersonation/stop`) and ends an expired impersonation
  without running the pending request (back to the profile as the impersonator).
- Refused meanwhile: re-authentication (so everything behind `reauth`: 2FA, API tokens, the
  permission matrix, groups, access, other impersonations), the password and the email change.
- Logging out ends the impersonation and the session. Start, end (`stopped`, `expired`,
  `logout`), refusals and blocked actions are security entries of the activity log.

## Settings (Settings › Security)

`Auth.minimumPasswordLength`, `absoluteLifetime`, `idleTimeout`, `rememberLifetime`, `captcha`,
`newDeviceNotice`, `loginAttemptsPerIp`, `loginAttemptsPerAccount`, `loginLockMinutes`,
`emailLinksPerIp`, `emailLinksPerAccount`, `reauthMinutes` (15, 1–240). The 2FA policy per group
is a column of the `groups` table (Users › Groups, D-15-02). Defaults live in `app/Config/Auth.php`. They are edited in
Settings › Security (`settings.security` + re-authentication, docs/settings.md); the minimum
password length can never go below `Config\Auth::$minimumPasswordLength` (`min_config`).

Keep `Config\Session::$expiration` at least as long as the absolute lifetime.

## For module developers

- `auth()->user()` / `auth('session')->user()` give the user; check permissions with `can:` and
  `$this->can()` (superadmins pass).
- After changing someone's groups or permissions call
  `(new \Modules\Auth\Services\SessionTracker())->privilegesChanged($userId)`, or use
  `GroupRules::assign()`/`remove()`.
- Send security emails with `Modules\Auth\Services\SecurityMailer`, never the queued channel.
- Protect sensitive actions with re-authentication: `'filter' => ['can:<permission>', 'reauth']`
  on the route (preferred), or inside a `BackendController` action:

  ```php
  if (($response = $this->requireReauth()) !== null) {
      return $response;
  }
  ```
- `(new \Modules\Auth\Services\TwoFactor())->method($userId)` gives a user's 2FA method
  (`email`, `totp` or null).
