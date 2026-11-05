# Auth module

**Purpose:** login (password, magic link), password reset, hardened sessions (absolute lifetime,
remember-me cap, idle lock, remote logout, new-device notices), the profile and "my sessions" on
top of Shield. Core module (cannot be disabled). Full description: `docs/auth.md`.

## Resources and permissions

- `auth-profile` (page): `view`, `edit` — granted to every group.
- `auth-sessions` (page): `view`, `revoke` — granted to every group.
- Settings `Auth.*` (defaults in `app/Config/Auth.php`, equal to the manifest): see `docs/auth.md`.

## Main pieces

- `Filters/AuthSession.php` (global `authsession`), `Filters/ForcePasswordReset.php` (`forcereset`).
- `Services/`: `AuthPolicy` (settings → `config('Auth')`), `LoginThrottle`, `Captcha`,
  `LinkTokens`, `SecurityMailer`, `SessionTracker`, `DeviceRecognizer`, `PasswordChanger`,
  `GroupRules`, `LoginListener` (Shield `login`/`logout` events in `Config/Events.php`).
- Tables: `user_profiles`, `user_sessions`, `known_devices`.
- Demo users (`php spark modules:demo Auth`): superadmin@, admin@, editor@, usuari@example.com,
  password `Demo-kpskeletor-2026`.

## Decisions

D-05-01 … D-05-08 in `docs/decisions.md`.

## Open tasks

- Session 06: 2FA (`UserTwoFactorAction`), re-authentication; theme Shield's action views.
- Session 07: replace the interim matrix of `Config\AuthGroups`; `backend` filter chain.
- Session 12: activity log entries instead of `log_message()`.
- Session 14: Settings › Security form for the `Auth.*` settings.
- Session 23: avatar with the file picker.

## How to test

`vendor/bin/phpunit modules/Auth` (feature tests on SQLite, base class `Tests/AuthTestCase.php`:
requests carry the session, cookies and a CSRF token; sent emails are captured).
