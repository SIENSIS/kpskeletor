# Session 31 — Internationalisation pass

Branch: `claude/amazing-goldberg-2trmeu` · Pull request: [SIENSIS/kpskeletor#39](https://github.com/SIENSIS/kpskeletor/pull/39) · Date: 2026-11-17

## What was built

- **Catalan is the default language** of the site (`Config\App::$defaultLocale = 'ca'`), Spanish
  the fallback, English the last resort, for the back office, the public site, the login, the web
  installer and the console (`php spark` now speaks Catalan by default).
- **Core language service** with the specification's fallback chain: requested locale → locale
  without region (`ca-ES` → `ca`) → `es` → `en`. A text missing in Catalan now shows the Spanish
  one (CodeIgniter's own class jumped straight to English).
- **Locale negotiation** for everybody: the user's profile language, then the browser's
  `Accept-Language`, then the site's default — always among the languages the site offers. The
  API negotiates the same way (without a profile).
- **Site languages in Settings › General:** "Default language" and "Available languages"
  (settings `Core.defaultLocale`, `Core.locales`). Restricting the languages also restricts the
  profile selector, the API and the emails.
- **"Translated text" setting type** (D-25-10): one text per available language, stored as one
  setting. The birthday greetings are now one setting `Users.birthdayGreeting` (stored values of
  the former `Users.birthdayGreetingCa|Es|En` are migrated).
- **Catalan system texts:** CodeIgniter's validation, pager, error pages, CSRF refusal and number
  texts, and Shield's texts (none of them exist in Catalan upstream).
- **CI key check** for every module, theme and manifest, every literal `lang('…')` key in the
  code, and the titles of widgets, notifications and tasks, in ca/es/en.
- Hard-coded / untranslated strings fixed: the Settings tabs showed the English module name
  ("Users", "Files", "News"…); every module now has `<Module>.module.name` in the three languages.
  Email templates now take `<html lang>` from the recipient's language.

## How it was built

- `modules/Core/I18n/`:
  - `Language` (extends CodeIgniter's; `chain()`, `getLine()` along the chain, `has()`), the
    `language` service in `app/Config/Services.php`;
  - `Locales` (supported / available / default, `names()`; reads settings only once installed);
  - `LocaleNegotiator` (profile → `Accept-Language` by quality and base language → default; invalid
    tags and `*` ignored);
  - `LocaleRules` (validation rule `supported_locales`, registered by the Core registrar).
- `Config\App`: `defaultLocale = 'ca'`, `supportedLocales = ['ca', 'es', 'en']`, new
  `localeFallbacks = ['es', 'en']`; `negotiateLocale` stays off.
- Filter `locale` (`modules/Core/Filters/Locale.php`), global before `csrf`/`authsession`, every
  path except `api/*` and `progress/*`. `authsession` applies the profile only when available;
  `apilocale` uses the negotiator; `RecipientLocale` (emails/notifications) falls back to
  `Locales::default()`; the profile form offers/validates only the available locales; menus use
  `Locales::default()`.
- Settings: `Core.defaultLocale` (select, empty = configuration) and `Core.locales` (list) in the
  Core manifest, group `general`; texts `Core.locales.*` (language autonyms), `Core.settings.*`,
  `Core.validation.supportedLocales`.
- Type `translated`: `ManifestValidator::SETTING_TYPES`, `SettingCaster` (map locale => text),
  `SettingDefinition` (a language-key default becomes the text of every locale),
  `Modules\Core\Settings\TranslatedText` (`get()`, `resolve()`, `defaults()`); Settings module:
  `Field` (cast keeps texts of locales no longer offered, `translation()`), `FormGenerator`
  (one input per available locale, `<field>[<locale>]`, rules per locale), view
  `modules/Settings/Views/fields/translated.php`.
- Users: manifest `birthdayGreeting` (type `translated`, default `Users.greetings.defaultMessage`),
  `BirthdayGreetings::message()` through `TranslatedText`; migration
  `modules/Users/Database/Migrations/2026-11-17-100001_MergeBirthdayGreetingSettings.php` (rows of
  the settings table only; `down()` splits them again).
- Catalan copies: `app/Language/ca/{Validation,Pager,Errors,Security,Number}.php` (plus `Time`
  from session 05) and `app/Language/ca/Auth.php` (Shield's keys not defined by the Auth module).
  `app/Language/en/Validation.php` (an empty override) removed.
- Tests: `tests/unit/I18n/LanguageKeysTest.php` (replaces `modules/Core/Tests/LanguageFilesTest.php`),
  `modules/Core/Tests/I18nTest.php`, `modules/Settings/Tests/TranslatedSettingsTest.php`, new cases
  in `modules/Users/Tests/BirthdayGreetingsTest.php`. `phpunit.xml.dist` sets
  `app.defaultLocale=en` (the suite asserts English texts).
- Docs: new `docs/i18n.md`; `docs/settings.md` (type `translated`), `docs/users.md`, `CLAUDE.md`
  (Languages), the Settings and Users `CLAUDE.md`. `deploy-status/screenshot.mjs` accepts a
  `locale` per shot.
- No new tables, routes, permissions or commands.

## Decisions and deviations

- **D-31-01 (decided by the owner, Decisions 31):** the two settings `Core.defaultLocale` /
  `Core.locales`, "Available languages" edited with checkboxes (new setting type `multiselect`).
- D-31-02 (tests run with English as the configured default), D-31-03 (Catalan copies only of the
  system files users see; Shield's in `app/Language/ca/Auth.php`), D-31-04 (semantics of the
  `translated` type), D-31-05 (browser negotiation, no guest language switcher), D-31-06 (file
  names; no `lang:check` command): accepted.
- Earlier gaps fixed because they blocked green tests (test isolation only, no product change):
  `InstallerTest` reuses the shared `storage` service, whose upload rules were read in an earlier
  test (now reset in `setUp`; it also failed on `main` when run right after a back-office test);
  `SettingsTestCase` resets the shared view renderer (an earlier test's sections leaked into the
  next settings page).

## How to try it

```bash
INSTALL_ADMIN_PASSWORD='Very-long-Passw0rd!x' php spark app:install --env=development \
  --url=http://localhost:8080/ --db=sqlite --email=root@example.org --2fa=none --demo --no-interaction
php spark serve
```

- `http://localhost:8080/login` with a Catalan, Spanish or English browser: the page follows the
  browser; any other language gets Catalan.
- Log in as `root@example.org` / `Very-long-Passw0rd!x`: Settings › General › "Idioma per
  defecte" / "Idiomes disponibles" (checkboxes; e.g. only `ca` and `es`: English disappears from the profile
  selector, and an English browser gets the default). Settings › Usuaris › "Felicitació": one text
  per available language.
- Profile › Idioma: the back office follows it.

## Screenshots

| File | What |
|---|---|
| `screenshots/01-settings-general-languages.png` | Settings › General with the default and available languages (tabs now translated) |
| `screenshots/02-settings-translated-greeting.png` | Settings › Users: the greeting as a translated text, one input per language |
| `screenshots/03-settings-translated-greeting-phone-dark.png` | The same at phone width in dark mode |
| `screenshots/04-login-guest-catalan-default.png` | Login for a guest with a Catalan browser |
| `screenshots/05-login-guest-spanish-browser.png` | Login for a guest whose browser asks for Spanish |
| `screenshots/06-login-guest-english-browser-phone.png` | Login, English browser, phone width |
| `screenshots/07-profile-languages.png` | Profile: the language selector (available languages only) |

Taken with `node deploy-status/screenshot.mjs deploy-status/31-i18n/screenshots.json` against
`php spark serve` with demo data, device scale factor 2. (The full-page shots show the sticky
"Save" bar in the middle of the page: a capture artefact.)

## Tests and checks

- `vendor/bin/phpunit`: **OK (1235 tests, 16282 assertions)**, 8.5 min (start of the session: 1213
  tests). New: `LanguageKeysTest` (6), `I18nTest` (10), `TranslatedSettingsTest` (6),
  `BirthdayGreetingsTest` (+2).
- `vendor/bin/phpstan analyse`: `[OK] No errors`.
- `vendor/bin/php-cs-fixer fix --dry-run --diff`: `Found 0 of 943 files that can be fixed`.
- `php spark csp:check`: no inline scripts, handlers or styles.
- `php spark app:install … --demo` (SQLite, development): completed, now in Catalan.

## Known issues

- JavaScript components keep English fallback texts used only when the server sends none
  (`dashboard.js`, `links-editor.js`, `tree-editor.js`).
- `php spark list` command descriptions and developer exception messages stay English.
- Content (pages, news, events) falls back to Catalan, not along the Spanish chain: Catalan is the
  required content language (unchanged).
- Composer still needs the known issue 04 workaround (`phpstan/phpstan` zip refused by the proxy:
  scratch copy of the lock pointing at a shallow clone of tag 2.3.5, `env -u COMPOSER_AUTH composer
  install --prefer-source`; project files unchanged).

## For the next session

- New modules: `Language/{ca,es,en}` with the same keys and `<Module>.module.name` when the module
  has settings; `LanguageKeysTest` also checks every literal `lang('…')` key and the manifest keys.
- Read the site's languages only through `Modules\Core\I18n\Locales`; per-language settings use
  type `translated` and `TranslatedText::get()`.
- Feature tests run in English (`app.defaultLocale=en`); test the languages explicitly.
- D-31-01 decided: `Core.locales` is a `multiselect` (checkboxes, at least one).
