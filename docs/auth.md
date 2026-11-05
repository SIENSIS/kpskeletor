# Authentication and sessions

The **Auth** module (`modules/Auth/`, a core module that cannot be disabled) adds login, magic
links, password reset, hardened sessions, the profile and "my sessions" on top of Shield.
Two-factor authentication and re-authentication arrive in session 06.

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

`auth-profile` and `auth-sessions` are granted to every group. `backend/*` also runs Shield's
`session` filter (login required) and `forcereset`.

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
- **Groups:** `superadmin`, `admin`, `editor`, `user` (`Config\AuthGroups`). Superadmins bypass
  `can:`; only a superadmin assigns or removes `superadmin`, the last one keeps it, nobody changes
  their own groups and the four groups cannot be renamed or deleted (`GroupRules`).

## Settings (Settings › Security, session 14)

`Auth.minimumPasswordLength`, `absoluteLifetime`, `idleTimeout`, `rememberLifetime`, `captcha`,
`newDeviceNotice`, `loginAttemptsPerIp`, `loginAttemptsPerAccount`, `loginLockMinutes`,
`emailLinksPerIp`, `emailLinksPerAccount`. Defaults live in `app/Config/Auth.php`.

Keep `Config\Session::$expiration` at least as long as the absolute lifetime.

## For module developers

- `auth()->user()` / `auth('session')->user()` give the user; check permissions with `can:` and
  `$this->can()` (superadmins pass).
- After changing someone's groups or permissions call
  `(new \Modules\Auth\Services\SessionTracker())->privilegesChanged($userId)`, or use
  `GroupRules::assign()`/`remove()`.
- Send security emails with `Modules\Auth\Services\SecurityMailer`, never the queued channel.
