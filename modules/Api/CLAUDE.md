# Api module

**Purpose:** the core of the JSON API under `/api/v1` with kpapy: app login with a second factor
(authenticator app or emailed code), refresh and logout, `api/v1/me`, personal tokens in the
profile, every user's tokens for administrators, the documentation page with "Try it". Core module
(cannot be disabled; D-10-01). Full description: `docs/api.md`.

## Resources and permissions

- `api` (crud, name `api`): `api.list`, `api.show`, `api.delete` (every user's tokens, kpcrudy
  CRUD at `backend/api/tokens`), `api.docs` (`backend/api/docs`), `api.tokens` (personal tokens at
  `backend/profile/api-tokens`, creation behind `reauth`). Default grants: admin `api.*`, editor
  `api.tokens`.
- Settings: `Api.publicSpec` (also `api/v1/openapi.json`), `Api.corsOrigins`, `Api.appTokenDays`.
  Rate limits are the Core's `Core.rateLimitApiToken` / `Core.rateLimitApiIp`.
- Menu: tokens and documentation in the `settings` zone, "My API tokens" in the `user` zone.

## Main pieces

- `app/Config/Kpapy.php`: shared resolver and mask key, deny by default, settings → limits.
- `Controllers/LoginController.php` (extends kpapy's: Auth's `LoginThrottle`, password-reset
  rule, personal tokens not refreshable), `LoginDenyController.php` (`auth/api-login/<id>`),
  `Me.php`, `Docs.php`, `PersonalTokens.php`, `Tokens.php` (kpcrudy).
- `TwoFactor/TotpProvider.php` (Auth's `TwoFactor::verifyTotp()` + recovery codes),
  `TwoFactor/EmailCodeProvider.php` (users with email 2FA and users without 2FA; D-10-02).
- `Auth/TokenAuthenticator.php` (records the last IP in `api_token_ips`), `Filters/ApiLocale.php`
  (`apilocale` on `api/*`), `Services/PersonalTokens.php`.
- Table `api_token_ips`. kpapy's own table `kpapy_login_challenges` is migrated by `modules:sync`.

## Decisions

D-10-01 … D-10-05 in `docs/decisions.md`.

## Open tasks

- Session 12: activity log entries for logins, refusals and token changes (now `log_message()`).
- Session 14: Settings › Security form for `Api.*`.
- Session 23: `avatar_url` in `me`.
- Session 26: Core endpoints (news, events, notifications, public ones) and the API catalogue
  check in `permissions:check`.

## How to test

`vendor/bin/phpunit modules/Api` (base class `Tests/ApiTestCase.php`: JSON requests without a
session, `tokenFor()`, `startLogin()`, a fresh cache; `ApiResourceTest` uses the sample resource
in `tests/_support/Api/`).
