# Session 48 — Contact module: a contact form protected against spam

> Run it by saying: **`executa deploy/48-contact.md`** (or `run deploy/48-contact.md`) in a
> Claude Code session on the project repository. You (Claude) are reading this file from the
> repository: follow it from start to end without waiting for more instructions.
>
> Added after the 1.0.0 release from the owner's requests of 2026-11-19 (a public contact form, safe and resistant to spam; decisions D-48-01 and D-48-02).

## Before starting: bring `main` up to date

1. `git fetch --all --prune && git checkout main && git pull`.
2. **Merge every finished session into `main`**, in order. A session is identified by its pull
   request title "Session NN: …" and by the branch recorded for it in `docs/progress.md` (it may
   be `session-NN-<slug>` or a branch assigned by the environment). For each session NN < 48
   whose branch is not yet contained in `main` (`git branch -r --no-merged origin/main`):
   - check it is finished: its `docs/progress.md` marks the session "done" and its
     `deploy-status/NN-*/README.md` exists; if not, stop and tell me to run
     `deploy/continue.md NN` first;
   - `git merge --no-ff origin/<its branch> -m "Merge session NN: <title>"`; resolve trivial
     conflicts (e.g. `docs/progress.md`); if a conflict touches code, stop and tell me;
   - run the checks (`composer install`, `vendor/bin/phpunit`, PHPStan, php-cs-fixer); if they fail,
     fix them on `main` in a separate commit or stop and tell me;
   - in `docs/progress.md` change that session's status to **merged**;
   - `git push origin main`, never with `--force`; if pushing to `main` is refused, stop and tell
     me to merge the pull request "Session NN: …" on GitHub, then run this file again.
3. Check that session 47 is now in `main` (marked merged in `docs/progress.md`). If it is not,
   stop and tell me which session must be finished first.
4. Work on a branch based on the updated `main`: if this environment assigned you a working branch,
   use it (merge `origin/main` into it if it does not contain it yet); otherwise create
   `session-48-contact`. Record the branch name for session 48 in `docs/progress.md` (add the
   row to the status table if it is not there).
5. Run `composer install` if needed, and the checks once to know the starting state.
6. Read `CLAUDE.md`, `docs/decisions.md`, `docs/plan.md` (section "48 — …"), `docs/progress.md`,
   the previous `deploy-status/` report, and these sections of `docs/specification.md`: "Security baseline (must be on by default)", "Cross-module services (Core)" (notifications, activity log), the Auth captcha (`docs/auth.md`).
   **Decisions in `docs/decisions.md` override the specification.**
7. If `docs/decisions.md` has **Open** entries marked (decide) that affect this session, stop and
   ask me to run `deploy/decide.md` first. Other open entries: list them in one line as a reminder
   and carry on.

## Goal

A `Contact` module: a public contact form (its own page and a section type for pages) whose
messages are stored and handled in the back office, with several layers against spam and without
turning the site into a mail relay.

**D-48-01 and D-48-02 must be decided** (names, URLs, settings; captcha policy and retention): if
one is still Open, stop and ask me to run `deploy/decide.md`.

## Scope

- **Form:** name, email, recipient (a list kept by the administrator: e.g. Secretaria, Direcció,
  FP; the real addresses never appear on the page), subject, message, a required privacy
  checkbox with a link to the privacy page (a setting). Its own public page (per D-48-01, a
  route with the language prefix of `LocaleUrls`) and a section type `contact.form` (sessions
  45–47) that may restrict the recipients shown.
- **Back office:** recipients (kpcrudy: name per language, email(s) or a group/permission to
  notify, order, active) and messages (kpcrudy list/show/delete, operations "mark answered",
  "archive"; recipients may be limited to the messages addressed to them by a record rule); the
  new-message notification type (bell and/or email per preferences, `notifyPermission()` or the
  recipient's users; queued); activity log; a dashboard widget with unread messages (optional).
- **Spam layers** (each a setting where it makes sense):
  1. honeypot field (CodeIgniter's `Honeypot`, or a module filter on the form route only);
  2. time trap: a signed timestamp (HMAC with the app key) in the form; too fast (< 3 s) or too old
     (> 2 h) is refused;
  3. the **Auth module's local captcha** (as the Tasks request form uses it): per D-48-02 always, or
     only after N messages from the same IP or email in a period;
  4. rate limits per IP and per email (`RateLimiter`, e.g. 3 per hour);
  5. content rules: at most N links (default 2), blocked words (setting), optional refusal of
     messages in unexpected scripts;
  6. every refusal is a `security_log()` entry with the reason; the visitor gets a generic message.
  No third-party captcha service (no outbound request in the request, D-48-02 records why).
- **Mail safety:** notification emails go from the site's address, never from the visitor's; the
  visitor's address only in `Reply-To`, validated, no CR/LF anywhere in headers; **no automatic
  reply to the visitor** (it could be abused to send mail to third parties); no attachments.
- **Data protection:** retention (D-48-02, proposal 365 days) with a scheduled task that deletes
  older messages; the privacy text and link; the IP stored only as long as needed for rate limits
  (or hashed), recorded in the docs.

## Tests

- Each spam layer refuses (honeypot, too fast, expired or forged timestamp, captcha wrong or
  missing, rate limit, too many links, blocked word) and logs; a normal message is stored and
  notifies the right recipients only.
- Forged POSTs: a recipient id that is inactive or not in the section's list; header injection in
  name/email/subject; HTML in the message (escaped in the back office and in the email).
- Back office permissions and record rules; retention task; migrations up/down/up; CSRF.
- e2e + axe of the form page and of a page with the form section (phone, desktop, dark); no CSP
  violation; screenshots.

## Docs

`docs/contact.md` (new), `docs/security.md` (spam layers, mail safety), `docs/sections.md`,
`docs/user-guide.md` (+ `ca`, `es`), `modules/Contact/CLAUDE.md`, `CHANGELOG.md` `[Unreleased]`.

## Done when

- Visitors can send a message that reaches the chosen recipients, spam is refused by the layers
  above, nothing can be used to send mail to third parties, and old messages are purged; with
  tests.
- All checks pass and the tests of this session are in the suite.

## Working rules for this session

- Follow `CLAUDE.md`. Read the listed sections of `docs/specification.md` **before** writing code,
  and the documentation of kpcrudy / kpapy in `vendor/siensis/` before using them.
- Stay inside this session's scope. If you find a gap in an earlier session's work, fix it only
  if it blocks this session, and note it in `docs/progress.md`.
- When the specification is ambiguous or contradictory, add an entry to `docs/decisions.md`
  (ID `D-48-01`, `D-48-02`…, with where, problem, proposal and alternatives; format in
  `deploy/decide.md`):
  - if it changes a public convention (permission names, manifest keys, URLs, settings names) or
    security, mark it **(decide)**, leave it **Open** and, if possible, build this session so the
    decision can still be changed (or stop if you cannot continue without it);
  - otherwise apply the most secure and simplest option consistent with the specification and
    record it as **Accepted (proposal)**.
- Texts in `ca`, `es` and `en` from the start; no inline JavaScript; tests for every rule you add.
- The skeleton is released (1.0.0): note every change under `[Unreleased]` in `CHANGELOG.md`
  (Added, Changed, Fixed); do not raise `Version::CORE` (the owner does it when releasing,
  `docs/updates.md`).

## Finish

1. Run the checks and show the results: `vendor/bin/phpunit`, `vendor/bin/phpstan analyse`,
   `vendor/bin/php-cs-fixer fix --dry-run --diff`, `php spark modules:sync`,
   `php spark permissions:check`, `php spark csp:check`, `php spark security:check`, `npm run e2e` against a demo site. Fix everything that fails.
2. **Status report** in `deploy-status/48-contact/` (see `deploy-status/README.md` for the format):
   - `README.md`: what was built, **how** (design, main classes and files, tables, routes,
     permissions, commands), decisions and deviations, how to try it (commands and URLs with the
     demo data), test results (counts and the checks' output summary), known issues and what the
     next session must know;
   - `screenshots/`: screenshots of what this session added that has a screen (back office,
     public site, phone width and dark mode when relevant), taken with Playwright against
     `php spark serve` with demo data, at device scale factor 2, PNG, named
     `01-<what>.png`, `02-…`, each one listed in the README with a caption. If the session has no
     screen, include the console output in the README instead; if screenshots are impossible in
     this environment, say why in the README.
3. Update `docs/progress.md`: status of session 48 = **done**, decisions, deviations, known
   issues, notes for the next session, the session's branch name and the link to
   `deploy-status/48-contact/`.
4. Commit in small commits on the session branch, push it (never with `--force`) and open a pull
   request titled exactly
   "Session 48: Contact module: a contact form protected against spam" with a summary, the checks' output and a link to the status report.
   No session follows this one: merge this branch into `main` yourself with the procedure of
   "Before starting" (checks, `docs/progress.md` → merged, push, never `--force`; if `main`
   cannot be pushed, stop and ask me to merge the pull request on GitHub).
5. Reply with a short report: done, not done (and why), risks, the open decisions (IDs and
   titles), and the next step: "run `deploy/decide.md`" when there are open (decide) entries,
   otherwise "the planned sessions are complete".
