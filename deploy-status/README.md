# Session status reports

Every session (`deploy/NN-<slug>.md`) ends by writing a report here, so the next session and the
reviewer know what exists and how to try it.

## Layout

```
deploy-status/
├── README.md                 this file (the format)
├── screenshot.mjs            Playwright screenshot helper (reused by every session)
└── NN-<slug>/                one folder per session, same slug as deploy/NN-<slug>.md
    ├── README.md             the report
    ├── screenshots/          01-<what>.png, 02-<what>.png, …
    └── screenshots.json      (optional) the screenshot.mjs config used
```

## Report format (`NN-<slug>/README.md`)

```markdown
# Session NN — <Title>

Branch: `<branch>` · Pull request: <link> · Date: YYYY-MM-DD

## What was built
Short list of the features delivered, in user terms.

## How it was built
Design; main classes and files; tables (migrations); routes; permissions; spark commands;
settings; events. Link to the code (`modules/Core/…`).

## Decisions and deviations
Every choice not dictated by the specification, and every deviation from it (also copied to
`docs/progress.md`).

## How to try it
Commands (`php spark …`), URLs, demo users and passwords (demo data only).

## Tests and checks
Results of `vendor/bin/phpunit`, PHPStan, php-cs-fixer and any session-specific checks
(paste the summary lines).

## Screenshots
![Caption](screenshots/01-<what>.png)
*01 — Caption explaining what the image shows.*

## Known issues
## Notes for the next session
```

## Screenshots

- PNG files `screenshots/01-<what>.png`, `02-<what>.png`, … in the order of the report.
- Taken with Playwright against `php spark serve` (http://localhost:8080) with **demo data**,
  device scale factor **2**; add **phone width** (390×844) and **dark mode** variants when the
  screen has a responsive or dark layout.
- Every image has a caption in the report. Sessions without screens (commands only) paste the
  console output instead.
- Never capture real personal data, secrets or tokens.

### `screenshot.mjs`

```bash
php spark serve &                       # in another terminal
node deploy-status/screenshot.mjs deploy-status/07-rbac/screenshots.json
# or inline:
node deploy-status/screenshot.mjs --out deploy-status/07-rbac/screenshots \
    --user admin@example.com --password 'demo-password' \
    --shot "/backend=01-dashboard" \
    --shot "/backend=02-dashboard-phone@390x844" \
    --shot "/backend=03-dashboard-dark:dark" \
    --shot "/=04-home-guest:guest:full"
```

Config file keys: `baseURL`, `outDir`, `deviceScaleFactor` (default 2), `locale` (default
`ca-ES`), `login` (`url`, `user`, `password`, `userField`, `passwordField`, `submit`, `waitFor`)
and `shots` (`url`, `name`, `width`, `height`, `dark`, `fullPage`, `waitFor`, `login` — `false`
for a guest, or another user). The script logs in once per user/viewport/colour scheme, prints
the HTTP status of every page and exits non-zero when a capture fails.

Requirements: `npm ci` (Playwright pinned in `package.json`) and Chromium (pre-installed in the
cloud image, or `npx playwright install chromium`).
