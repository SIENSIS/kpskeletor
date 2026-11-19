# Session 44 — Web Push notifications

> Run it by saying: **`executa deploy/44-web-push.md`** (or `run deploy/44-web-push.md`) in a
> Claude Code session on the project repository. You (Claude) are reading this file from the
> repository: follow it from start to end without waiting for more instructions.
>
> Added after the 1.0.0 release from the owner's requests of 2026-11-19 (a free push channel to phones and desktops, instead of WhatsApp; decisions D-44-01 and D-44-02).

## Before starting: bring `main` up to date

1. `git fetch --all --prune && git checkout main && git pull`.
2. **Merge every finished session into `main`**, in order. A session is identified by its pull
   request title "Session NN: …" and by the branch recorded for it in `docs/progress.md` (it may
   be `session-NN-<slug>` or a branch assigned by the environment). For each session NN < 44
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
3. Check that session 43 is now in `main` (marked merged in `docs/progress.md`). If it is not,
   stop and tell me which session must be finished first.
4. Work on a branch based on the updated `main`: if this environment assigned you a working branch,
   use it (merge `origin/main` into it if it does not contain it yet); otherwise create
   `session-44-web-push`. Record the branch name for session 44 in `docs/progress.md` (add the
   row to the status table if it is not there).
5. Run `composer install` if needed, and the checks once to know the starting state.
6. Read `CLAUDE.md`, `docs/decisions.md`, `docs/plan.md` (section "44 — …"), `docs/progress.md`,
   the previous `deploy-status/` report, and these sections of `docs/specification.md`: "Cross-module services (Core)" (notifications), "Front-end assets and JavaScript" (PWA), "Security baseline (must be on by default)" (outbound requests), "Installer and CLI".
   **Decisions in `docs/decisions.md` override the specification.**
7. If `docs/decisions.md` has **Open** entries marked (decide) that affect this session, stop and
   ask me to run `deploy/decide.md` first. Other open entries: list them in one line as a reminder
   and carry on.

## Goal

A third notification channel, **`push`**: users who turn it on in a browser or an installed PWA
(phone or desktop) receive the notifications they chose as system notifications, even with the
site closed. Free (no provider account), standard Web Push with VAPID, implemented in the Core
without new dependencies (D-44-01), sent from the queue through `OutboundClient`.

Read D-44-01 and D-44-02 in `docs/decisions.md` first. **D-44-02 must be decided** (names, URLs,
settings and the security rules below are its proposal): if it is still Open, stop and ask me to
run `deploy/decide.md`.

## Findings (2026-11-19)

- Channels are a fixed list: `NotificationType::CHANNELS = ['database', 'email']`, used by
  `Notifier` (defaults, preferences, delivery). The preferences screen shows one checkbox per
  channel. Email is queued in `mail_queue` and sent by a scheduled task (`docs/notifications.md`).
- The Core's service worker (`modules/Core/Assets/pwa/sw.js`, served at `/sw.js`) caches nothing
  and is registered by `core/pwa.js` only when the page links the manifest (PWA setting on).

## Scope

### Web Push in the Core, without dependencies (D-44-01)

- `Modules\Core\Push\` (or the Notifications namespace): **payload encryption** per RFC 8291
  (`aes128gcm` content coding, RFC 8188: ECDH P-256 with the subscription's `p256dh`, HKDF-SHA256
  with its `auth` secret, AES-128-GCM, one record) and **VAPID** per RFC 8292 (JWT ES256 signed with
  the site's private key, `Authorization: vapid t=…, k=…`), with PHP's OpenSSL only. Verify it
  with the **test vectors of RFC 8291** (appendix A) and RFC 8292, and by decrypting in the test
  with the receiver's keys; record the sources in the code's docblocks.
- Keys: `.env` `push.vapidPublicKey`, `push.vapidPrivateKey` and `push.subject` (a `mailto:` or
  `https:` contact). `app:install` generates them; `php spark push:keys [--force]` generates or
  rotates them (rotating invalidates every subscription: it asks and then deletes them).
  `security:check`: warning when push is on without keys, info otherwise.

### The channel

- `NotificationType::PUSH = 'push'` added to the channels (the decision may make the list
  extensible instead; follow it). Default channels of the existing types unchanged (push is
  opt-in per device anyway); preferences get a third column, shown only when push is enabled for
  the site.
- Table `push_subscriptions` (one migration): user, endpoint (and a unique hash of it), `p256dh`,
  `auth`, a device label (browser and system from the user agent, editable), created, last used,
  failures. Table `push_queue` (one migration) like `mail_queue`: recipient subscription, payload
  (JSON, before encryption), attempts, next attempt, status.
- Delivery: `Notifier` renders the message (title, short text, internal URL, icon) in the
  recipient's language, queues one row per subscription; a scheduled task "Send the queued push
  notifications" (every minute, like email) encrypts and sends through `OutboundClient` (`TTL`,
  `Urgency` high for mandatory types); `404`/`410` delete the subscription, `429`/`5xx` retry with
  backoff, other errors count failures (a subscription with too many failures is removed). Never
  sent inside a web request.
- Payload privacy (setting): `full` (title and text) or `generic` ("You have a new notification",
  the text only after opening the site) — messages are end-to-end encrypted to the browser, but a
  lock screen shows them.

### Security (D-44-02)

- **Endpoints only of known push services** (SSRF): a subscription is accepted only if its
  endpoint is `https://` on a host of `Config\Push::$serviceHosts` (Google FCM, Mozilla, Microsoft
  WNS, Apple — check the current hosts and record them) and is not a private address; those hosts
  are added to the outbound allow-list by the Core. A forged subscription to another host is
  refused (test).
- The subscription endpoints are personal routes of the user (resource and permission per the
  decision; CSRF; `ratelimit:forms`); a user only lists and deletes their own devices; deleting the
  user deletes their subscriptions; logging out offers to keep or remove the device's subscription.
- The service worker opens only same-origin URLs on `notificationclick`; the payload never contains
  secrets or tokens.

### Front end

- `sw.js`: `push` (show the notification with title, body, icon, badge, `data.url`, a tag per
  type) and `notificationclick` (focus an open tab of the site or open the URL; same origin only).
  Keep "caches nothing".
- Profile › Notifications: "Activate notifications on this device" (permission asked only on that
  click), the list of the user's devices with "Send a test" and "Remove", and the state of this
  device; clear messages when the browser does not support push, when permission was denied, and
  on iPhone/iPad (only from the installed PWA, iOS/iPadOS 16.4+). If the PWA setting is off,
  register the service worker for push anyway (record how).
- ES module `core/push.js` (`data-component`), texts ca/es/en, accessible, phone width, dark mode,
  no inline code.

### Settings

- Core settings (Settings › Notifications or Security, per the decision): push enabled (default
  off), payload privacy; outbound hosts as above.

## Tests

- Crypto: RFC 8291/8292 vectors; round trip (encrypt with a test subscription's keys, decrypt in
  the test); JWT verifies with the public key and has `aud` = the endpoint's origin, `exp` ≤ 24 h.
- Subscription endpoint: valid push host accepted; other host, `http:`, private IP refused;
  another user's device cannot be listed or deleted; CSRF and rate limit.
- Delivery with `FakeTransport` through the real `OutboundClient`: one request per subscription
  with the right headers, `410` removes the subscription, `5xx` retries, nothing sent inline;
  preferences respected, mandatory types always; `generic` privacy hides the text.
- `push:keys` and `app:install` keys; `security:check` warning; migrations up/down/up.
- e2e: Chromium supports push in Playwright (grant `notifications`): subscribe, send a test from
  the task with a fake push service, the service worker shows it; screenshots of the profile
  section (desktop, phone, dark).

## Docs

`docs/notifications.md` (the `push` channel), `docs/frontend.md` (service worker), `docs/security.md`
(push endpoints, keys), `docs/commands.md` (`push:keys`), `docs/user-guide.md` (+ `ca`, `es`),
installation notes (keys, iOS), `CHANGELOG.md` `[Unreleased]`.

## Done when

- A user can enable push on a device, choose which notifications go there, receive them with the
  site closed and remove the device; everything goes through the queue and `OutboundClient`, with
  the tests above.
- All checks pass and the tests of this session are in the suite.

## Working rules for this session

- Follow `CLAUDE.md`. Read the listed sections of `docs/specification.md` **before** writing code,
  and the documentation of kpcrudy / kpapy in `vendor/siensis/` before using them.
- Stay inside this session's scope. If you find a gap in an earlier session's work, fix it only
  if it blocks this session, and note it in `docs/progress.md`.
- When the specification is ambiguous or contradictory, add an entry to `docs/decisions.md`
  (ID `D-44-01`, `D-44-02`…, with where, problem, proposal and alternatives; format in
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
2. **Status report** in `deploy-status/44-web-push/` (see `deploy-status/README.md` for the format):
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
3. Update `docs/progress.md`: status of session 44 = **done**, decisions, deviations, known
   issues, notes for the next session, the session's branch name and the link to
   `deploy-status/44-web-push/`.
4. Commit in small commits on the session branch, push it (never with `--force`) and open a pull
   request titled exactly
   "Session 44: Web Push notifications" with a summary, the checks' output and a link to the status report.
   The next session merges it into `main` (review it before running the next one if you want).
5. Reply with a short report: done, not done (and why), risks, the open decisions (IDs and
   titles), and the next step: "run `deploy/decide.md`" when there are open (decide) entries,
   otherwise "run `deploy/next.md`".
