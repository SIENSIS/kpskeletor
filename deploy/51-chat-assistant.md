# Session 51 — Chat assistant: FAQ and site search in a chat bubble, without external services

> Run it by saying: **`executa deploy/51-chat-assistant.md`** (or `run deploy/51-chat-assistant.md`) in a
> Claude Code session on the project repository. You (Claude) are reading this file from the
> repository: follow it from start to end without waiting for more instructions.
>
> Added after the 1.0.0 release from the owner's request of 2026-11-19 (the classic chat icon, without an external service: option A, frequently asked questions plus the site's own content; decisions D-51-01 and D-51-02).

## Before starting: bring `main` up to date

1. `git fetch --all --prune && git checkout main && git pull`.
2. **Merge every finished session into `main`**, in order. A session is identified by its pull
   request title "Session NN: …" and by the branch recorded for it in `docs/progress.md` (it may
   be `session-NN-<slug>` or a branch assigned by the environment). For each session NN < 51
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
3. Check that session 50 is now in `main` (marked merged in `docs/progress.md`). If it is not,
   stop and tell me which session must be finished first.
4. Work on a branch based on the updated `main`: if this environment assigned you a working branch,
   use it (merge `origin/main` into it if it does not contain it yet); otherwise create
   `session-51-chat-assistant`. Record the branch name for session 51 in `docs/progress.md` (add the
   row to the status table if it is not there).
5. Run `composer install` if needed, and the checks once to know the starting state.
6. Read `CLAUDE.md`, `docs/decisions.md`, `docs/plan.md` (section "51 — …"), `docs/progress.md`,
   the previous `deploy-status/` report, and these sections of `docs/specification.md`: "Modules" (Contact, Pages, News), "Front-end assets and JavaScript", "Security baseline (must be on by default)" (rate limits, CSP), the i18n sections, and `docs/search.md` (session 50).
   **Decisions in `docs/decisions.md` override the specification.**
7. If `docs/decisions.md` has **Open** entries marked (decide) that affect this session, stop and
   ask me to run `deploy/decide.md` first. Other open entries: list them in one line as a reminder
   and carry on.

## Goal

The classic **chat bubble** of many sites, with no external service: a floating button on the
public site opens a small chat panel; the visitor writes a question and the assistant answers
from **frequently asked questions** managed in the back office and from the **site search index**
(session 50), with links to the pages and news found; when nothing fits it offers the contact
form (session 48). No AI, no outbound request, works on any hosting.

**D-51-01 and D-51-02 must be decided** (names, URLs, settings; what is stored and for how long):
if one is still Open, stop and ask me to run `deploy/decide.md`.

## Findings (2026-11-19)

- Session 50 builds the Core search index with visibility per visitor (`service('search')`);
  session 48 the `Contact` module with layered anti-spam (honeypot, time trap, rate limits,
  content rules, local captcha) and its public page.
- The rules: no outbound HTTP on regular requests, strict CSP (no inline code), texts in
  ca/es/en, public URLs with the language prefix.

## Scope

### Module and FAQ (D-51-01)

- A module (name as decided, e.g. `Assistant`), disabled by default, with a kpcrudy CRUD of
  **frequently asked questions**: question and answer per site language (`ContentTranslations`;
  answer as rich text through the Core editor and `HtmlSanitizer`), extra phrasings/keywords,
  optional links (page, module link type, URL validated like menu items), audience (everyone,
  signed-in users, groups), active flag and order. FAQs are also a source of the search index
  (session 50 contract), so the site search finds them too.
- Settings (as decided): on/off of the public bubble, greeting text (translated), number of
  results shown, which index sources it may answer from, whether to offer the contact form.

### Answering

- One POST endpoint (CSRF, rate limit per IP and session, maximum length) returns JSON: the best
  FAQ match (its answer) when its score passes a threshold, otherwise up to N search results
  (title, summary, URL), otherwise "I could not find it" with the contact form link. Matching
  uses the index's normalisation (language, accents, stop words) and the visitor's language and
  visibility (a private page never appears in an answer to a guest).
- Suggested questions (the top FAQs) as buttons when the panel opens.
- No conversation is stored by default; if D-51-02 allows it, unanswered questions are logged
  anonymised (no IP, no user id) so editors can write new FAQs, and purged by a scheduled task.

### The bubble (front end)

- A theme partial and an ES module (`data-component`): a floating button (accessible name,
  visible focus, not covering the cookie notice or the PWA prompt), a panel as a dialog with the
  conversation in a list with `aria-live="polite"`, keyboard (Enter sends, Escape closes, focus
  returns to the button), phone width (full-width sheet), dark mode, `prefers-reduced-motion`;
  links in answers are normal links; the panel remembers being open only in `sessionStorage`.
- Nothing inline (CSP); the JSON answer is rendered with DOM methods (no `innerHTML` of user
  text); the FAQ answer HTML is already sanitised on the server.

## Tests

- Matching: an FAQ found by a paraphrase and by its keywords, per language; search results when no
  FAQ passes the threshold; the contact form offered last; visibility (guest vs member vs group)
  through FAQs and search results; disabled module answers 404.
- Endpoint security: CSRF, rate limit (429), long and malicious input, no HTML injection in the
  answer, nothing stored unless the setting allows it; the purge task.
- Permissions of the FAQ CRUD, forged POSTs, migrations up/down/up, demo FAQs in the demo seeder.
- e2e: open the bubble, ask, get an answer with a link, keyboard only, phone, dark, axe,
  `csp:check`.
- Screenshots: the bubble, an answer from an FAQ, search results, "not found" with the contact
  link (desktop, phone, dark), the FAQ CRUD.

## Docs

The module's `CLAUDE.md` and a `docs/assistant.md` (or the decided name), `docs/search.md`
(FAQs as a source), `docs/user-guide.md` (+ `docs/ca/`, `docs/es/`), `docs/security.md`
(endpoint, what is stored), `CHANGELOG.md` `[Unreleased]`.

## Done when

- Visitors can ask the bubble and get FAQ answers, links to visible content or the contact form,
  with nothing sent outside the server; with tests and docs.
- All checks pass and the tests of this session are in the suite.

## Working rules for this session

- Follow `CLAUDE.md`. Read the listed sections of `docs/specification.md` **before** writing code,
  and the documentation of kpcrudy / kpapy in `vendor/siensis/` before using them.
- Stay inside this session's scope. If you find a gap in an earlier session's work, fix it only
  if it blocks this session, and note it in `docs/progress.md`.
- When the specification is ambiguous or contradictory, add an entry to `docs/decisions.md`
  (ID `D-51-03`, `D-51-04`…, with where, problem, proposal and alternatives; format in
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
2. **Status report** in `deploy-status/51-chat-assistant/` (see `deploy-status/README.md` for the format):
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
3. Update `docs/progress.md`: status of session 51 = **done**, decisions, deviations, known
   issues, notes for the next session, the session's branch name and the link to
   `deploy-status/51-chat-assistant/`.
4. Commit in small commits on the session branch, push it (never with `--force`) and open a pull
   request titled exactly
   "Session 51: Chat assistant: FAQ and site search in a chat bubble, without external services" with a summary, the checks' output and a link to the status report.
   No session follows this one: merge this branch into `main` yourself with the procedure of
   "Before starting" (checks, `docs/progress.md` → merged, push, never `--force`; if `main`
   cannot be pushed, stop and ask me to merge the pull request on GitHub).
5. Reply with a short report: done, not done (and why), risks, the open decisions (IDs and
   titles), and the next step: "run `deploy/decide.md`" when there are open (decide) entries,
   otherwise "the planned sessions are complete".
