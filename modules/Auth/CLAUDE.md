# Auth module

**Purpose:** login (password, magic link), password reset, hardened sessions (absolute lifetime,
remember-me cap, idle lock, remote logout, new-device notices), two-factor authentication (email
or app, recovery codes, group policy), re-authentication ("sudo mode"), the profile and "my
sessions" on top of Shield. Core module (cannot be disabled). Full description: `docs/auth.md`.

## Resources and permissions

- `auth-profile` (page): `view`, `edit` — granted to every group.
- `auth-sessions` (page): `view`, `revoke` — granted to every group.
- 2FA screens (`backend/profile/two-factor/*`) use `auth-profile` (+ `reauth` for changes).
- Settings `Auth.*` (defaults in `app/Config/Auth.php`, equal to the manifest): see `docs/auth.md`.

## Main pieces

- `Filters/AuthSession.php` (global `authsession`), `Filters/ForcePasswordReset.php` (`forcereset`),
  `Filters/ForceTwoFactor.php` (`force2fa`), `Filters/RequireReauth.php` (`reauth`).
- `Authentication/UserTwoFactorAction.php` (Shield's `login` action, routes `auth/a/*`),
  `Authentication/SessionAuthenticator.php` (remember-me only after the 2FA step).
- `Services/`: `AuthPolicy` (settings → `config('Auth')`), `LoginThrottle`, `Captcha`,
  `LinkTokens`, `SecurityMailer`, `SessionTracker`, `DeviceRecognizer`, `PasswordChanger`,
  `GroupRules`, `LoginListener` (Shield `login`/`logout` events in `Config/Events.php`),
  `TwoFactor` (method, encrypted TOTP secret, reset), `Totp` (otphp + QR SVG), `RecoveryCodes`,
  `EmailCodes`, `Reauth`.
- Tables: `user_profiles`, `user_sessions`, `known_devices`, `user_two_factor`,
  `user_recovery_codes`.
- Demo users (`php spark modules:demo Auth`): superadmin@, admin@, editor@, usuari@example.com,
  password `Demo-kpskeletor-2026`.

## Decisions

D-05-01 … D-05-08 and D-06-01 … D-06-06 in `docs/decisions.md` (D-06-04: a password login counts
as re-authentication, decided by the owner).

## Open tasks

- Session 07: replace the interim matrix of `Config\AuthGroups`; `backend` filter chain.
- Session 10: kpapy 2FA providers reading `TwoFactor::method()` and the TOTP secret.
- Session 11: notify the user of an admin 2FA reset (`twoFactorReset` event).
- Session 12: activity log entries instead of `log_message()` (2FA changes, re-authentication).
- Session 14: Settings › Security form for the `Auth.*` settings.
- Session 15: 2FA reset row operation, policy in the `groups` table, refuse `reauth` while
  impersonating.
- Session 23: avatar with the file picker.

## How to test

`vendor/bin/phpunit modules/Auth` (feature tests on SQLite, base class `Tests/AuthTestCase.php`:
requests carry the session, cookies and a CSRF token; sent emails are captured;
`Tests/TwoFactorTestCase.php` adds an encryption key, a fixed clock and TOTP helpers).
