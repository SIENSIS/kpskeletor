# Session 35 — Quality: end-to-end, accessibility and CI

Branch: `claude/amazing-wozniak-r3ycn2` (continues `claude/lucid-babbage-qm6oxf`, which stopped
half way) · Pull request: see `docs/progress.md` · Date: 2026-11-18

## What was built

- **End-to-end and accessibility tests** (`npm run e2e`, Playwright 1.63 + axe-core 4.14):
  `playwright.config.mjs` and `tests/e2e/` (`global-setup.mjs`, `support.mjs`,
  `public.spec.mjs`, `backoffice.spec.mjs`). Six projects — phone 390×844, tablet 820×1180,
  desktop 1440×900, each in light and dark mode — open 6 public pages as a guest and 21 back-office
  screens as the administrator. Every page fails on status ≥ 400, a redirect to the login,
  horizontal overflow, a CSP violation, or **any** axe violation of WCAG 2.0/2.1 A and AA. One test
  follows every link of the generated menus (desktop), and two keyboard tests check the skip links.
  Screenshots: `writable/e2e/screenshots/<project>/<screen>.png`.
- **CI** (`.github/workflows/ci.yml`): new job *End-to-end and accessibility* (demo site on SQLite
  with Tasks enabled, `npm run e2e`, artifact `e2e`); new job *Secret scanning* (gitleaks 8.28.0
  release binary, checksum-verified, whole history, `.gitleaks.toml`); `assets:publish --check`
  now also checks `public/assets/vendor` against `vendor.json` (`VendorChecksums`, also used by
  `security:check`'s `dependencies.vendor`); Dependabot for npm. Existing jobs unchanged: PHP
  8.2/8.3/8.4 with composer validate + audit, php-cs-fixer, PHPStan 6, PHPUnit SQLite, CSP check,
  fresh install; MariaDB PHPUnit + production install.
- **WCAG AA in both modes** (found by axe, fixed at the source, D-35-04): darker text shades for
  links and outline buttons in light mode and `*-text-emphasis` shades in dark mode
  (`themes/default/Assets/css/theme.css`); calendar selected tab, other-month days and black/white
  event text (`contrastColor`, `EventRepository::textColor()`); ARIA names of the editor's pickers;
  `role="img"` on labelled pin icons; `role="button"` on the dashboard's toggle link; scrolled
  tables become keyboard-focusable (`core/ui/scroll-regions.js`).
- **Bug fixed:** `backend/events` answered 500 when an event was managed by the Tasks module
  (`lang('Tasks.module')` is a group of keys; `CalendarView::moduleLabel()` now reads
  `<Module>.module.name`).
- **One minisign verifier** `Modules\Core\Security\Minisign` (+ `MinisignRefused`);
  `SignatureCheck` (packages) and `SignatureVerifier` (releases) are adapters with their own
  messages (D-35-03). The test signer moved to `tests/_support/Security/TestSigner.php`.
- **Owner's request:** News and Pages give the editor to the translated body with
  `setTranslations(..., ['attrs' => ['body' => RichTextEditor::attrs(…)]])` (kpcrudy 1.3)
  instead of the undocumented `fieldType('body', 'translation', …)`.
- **Missing specification tests** (from an audit of "Quality, tests and delivery"): the reauth
  route list is now complete and must match every route with `reauth`; every sampled response
  (public, login, back office, API) carries the strict CSP; each named rate limit answers 429 and
  is attached to its routes, plus a real 429 on `news/feed.xml`; tampered upload-token types and
  size are refused; a byte-modified backup is refused by `restore()` with nothing touched; admins'
  and superadmins' personal iCal feeds leave out restricted events they are not in.

## How it was built

- The e2e site is installed beforehand (`app:install --demo`, `--url=http://localhost:8089/`), so
  the tests never rewrite a developer's `.env`. The login form is rate limited: the administrator
  logs in once in the global setup, and each test writes the refreshed cookie back (the app
  rotates session ids, which killed later tests otherwise). One worker against `php spark serve`.
- axe is injected with `page.evaluate()` through the DevTools protocol, so the page's CSP stays
  strict (no `bypassCSP`) and CSP violations are still caught.
- Contrast fixes change only text colours (`--kp-primary-text` = primary + 15 % black; Bootstrap's
  next palette shades); the `primaryColor` option and the palette stay. Plain links needed
  `a:not([class]) { color: var(--bs-link-color) }` because Bootstrap colours them through
  `--bs-link-color-rgb`.
- FullCalendar 7 reads `contrastColor` (not `textColor`) per event.
- New/changed files: `modules/Core/Security/{Minisign,MinisignRefused}.php`,
  `modules/Core/Frontend/VendorChecksums.php`, `modules/Core/Assets/js/ui/scroll-regions.js`,
  `themes/default/Assets/css/theme.css`, `modules/Events/{Services/EventRepository.php,Controllers/CalendarView.php,Assets/css/calendar.css}`,
  `modules/Core/Assets/js/forms/editor.js`, `.github/workflows/ci.yml`, `.github/dependabot.yml`,
  `.gitleaks.toml`, `package.json`, `playwright.config.mjs`, `tests/e2e/*`. No tables, routes,
  permissions or commands added.

## Decisions and deviations

- D-35-01 e2e against a pre-installed demo site; any axe A/AA violation fails (no allow-list).
- D-35-02 gitleaks release binary instead of the action (licence for organisations).
- D-35-03 one minisign verifier in the Core.
- D-35-04 WCAG AA colours through darker text shades.
- Deviation: screens behind `reauth` (Settings › Security, users' access, restore…) are not in the
  e2e run (the session never re-authenticates); their markup uses the same components.

## How to try it

```bash
npm ci
INSTALL_ADMIN_PASSWORD='…' php spark app:install --env=development --url=http://localhost:8089/ \
    --db=sqlite --db-name=e2e.db --email=admin@example.com --demo --2fa=none --no-interaction
php spark modules:enable Tasks && php spark modules:demo Tasks
E2E_USER=admin@example.com E2E_PASSWORD='…' npm run e2e
```

(In a checkout you develop in, do it in a copy: `app:install` writes `.env`.) Then
`http://localhost:8089/backend/calendar`, `backend/events`, `backend/news/posts` in both modes.

## Tests and checks

- Full suite before the last fix: `Tests: 1327, Assertions: 17750, Errors: 1` (12:32; the one error
  was the new managing-module test publishing to a calendar Tasks does not own; fixed, then
  `phpunit modules/Events`: `OK (78 tests, 680 assertions)`). Session 34: 1305 tests.
- e2e: `163 passed, 5 skipped (5.6m)` (the menu crawl runs only at desktop-light), then the
  keyboard tests `12 passed`.
- PHPStan level 6: `[OK] No errors`. php-cs-fixer: `Found 0 of 1001 files that can be fixed`.
  `php spark csp:check`: no inline code. `php spark assets:publish --check`: up to date (vendor
  checksums included). `composer validate --strict`: valid.
- CI on the branch: see the pull request (the `secrets` job could not be tried locally).

## Screenshots

Taken with `deploy-status/screenshot.mjs` against the e2e demo site, scale 2.

| File | What |
|---|---|
| `screenshots/01-news-posts-contrast-light.png` | News list, light: AA links and outline buttons |
| `screenshots/02-news-posts-contrast-dark.png` | The same in dark mode (lighter outline-button texts) |
| `screenshots/03-calendar-event-text-light.png` | Calendar: selected tab tint, black text on blue and orange events |
| `screenshots/04-events-list-managed-by-tasks.png` | Events administration with Tasks-managed events (was a 500) |
| `screenshots/05-editor-dark.png` | News form with the rich-text editor (setTranslations `attrs`), dark |
| `screenshots/06-security-status-phone-dark.png` | Security status at phone width, dark (focusable scrolled tables) |
| `screenshots/07-public-calendar-phone.png` | Public calendar at phone width |
| `screenshots/08-modules-outline-buttons-dark.png` | Modules screen, dark: outline secondary/danger buttons |

## Known issues

- gitleaks was not run locally (download refused here); the CI job is its only check.
- Screens behind `reauth` are not covered by the e2e tests.
- The full PHPUnit suite takes ~12.5 minutes in this environment.
- Composer needed the known issue 04 workaround again (phpstan's zip seeded from a shallow clone
  of tag 2.3.5, then `env -u COMPOSER_AUTH composer install --prefer-source`).

## Notes for the next session

- Session 36 can reuse `npm run e2e` for acceptance (`E2E_BASE_URL` for an already running site).
- Add new screens to `tests/e2e/backoffice.spec.mjs`; keep the theme text shades AA when the
  palette changes (`docs/themes.md`, "Contrast").
