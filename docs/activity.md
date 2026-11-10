# Activity log

The append-only activity and security log (specification "Activity log" and "Modules" ›
"Activity"; decisions D-12-01 … D-12-05). The **Core** writes it (table `activity_log`,
`ActivityLogger`, retention task) and keeps writing it while the **Activity** module, which only
shows it, is disabled.

## What is logged

| Kind | How | Type |
|---|---|---|
| Every kpcrudy CRUD write: insert, update (changed fields only), delete, restore, bulk actions, CSV imports | automatically, `AuditedCrud` (made by `CrudFactory`) | activity |
| Custom operations (`addOperation()` with `method` `post`) and custom bulk actions | automatically, after the callback (not when it returns `false`) | activity |
| CSV exports | automatically | activity |
| Logins, logouts, remember-me logins, profile changes, group membership, module enable/disable, demo data, "run now", failed scheduled tasks, notification preferences, outbound requests | `activity_log()` in the code that does it | activity |
| Failed logins, 2FA codes and re-authentications, throttling (`429`), permission denials (`403` from `can:`), re-authentication, password/email/2FA changes, recovery codes, grants and user permissions, token creation and revocation, a token used from a new IP address, app logins denied, refused outbound requests | `security_log()` | security |

Refused uploads (session 13), settings changes (session 14: `Settings`/`settings_changed`, `security_settings_changed`, `security_settings_refused`, `test_email`, `caches_cleared`, `assets_published`) and refused packages (session 29) are
written by the sessions that build them, with `security_log()` / `activity_log()`.

## Writing entries

```php
activity_log('News', 'publish', [
    'resource'  => 'news-posts',  // the CRUD resource, if any
    'record_id' => $id,
    'summary'   => $title,        // data: one line, 255 characters
    'before'    => ['status' => 'draft'],
    'after'     => ['status' => 'published'],
]);

security_log('Storage', 'upload_refused', ['summary' => $originalName, 'changes' => ['reason' => 'mime']]);
```

- `module` is the module's name; `action` a code (`[A-Za-z0-9_.:-]`, 64 characters). Its label is
  the language key `<Module>.activity.<action>` (in the module's language file, `ca`/`es`/`en`),
  then `Activity.actions.<action>` (the generic CRUD actions), else the code.
- `user_id` defaults to the logged-in user (pass it for events without a session, e.g. the user
  of a login, or `null`). The IP address and the user agent are taken from the web request (none
  in `spark` commands and scheduled tasks).
- `before`, `after` and `changes` are stored as JSON with every key matching
  `/pass|pwd|secret|token|key|csrf|otp/i` masked (`********`) at any depth (`SecretMasker`).
  Long values are shortened (1000 characters, 60 KB per entry).
- Never put secrets or whole request bodies in `summary`. A failed login keeps the typed login
  name only when it is a valid email address (`ActivityLogger::typedEmail()`).
- Writing never breaks the action: a database failure goes to the application log.
- Do not log a second entry for a CRUD write: it is already logged. `->skipActivity('key')` on a
  CRUD leaves out operations that only change the user's own view (marking a notification read)
  or that write a richer entry themselves ("run now", Users' ban/force reset/2FA reset/logout/
  impersonate). Skip every operation that can return without acting (e.g. a `requireReauth()`
  redirect) and log it in the service when it happens: `AuditedCrud` logs whatever the callback
  returns except `false`.
- Every logged custom operation and bulk action needs a label `<Module>.activity.<key>` (or a
  generic `Activity.actions.<key>`) in ca, es and en; `tests/feature/Crud/ActivityLabelsTest.php`
  scans the modules' controllers and fails on a missing one.

## Log-injection safety

- Values are stored in their own columns, never interpolated into a text, so user input cannot
  create or alter entries; the summary loses control characters and line breaks.
- The application (file) log uses `Modules\Core\Log\SafeLogger` (`app/Config/Services.php`):
  every `{placeholder}` value of `log_message()` is written with `\n`, `\r`, `\t`, `\xNN` escaped.

## Append-only and retention

- No screen, route or API edits or deletes entries (`readOnly()`, no `add`/`edit`/`delete`
  permissions in the catalogue; tests post forged requests).
- `Core.activityRetentionDays` (default 365, 1–3650; Settings › Security): the Core scheduled
  task `PurgeActivityLog` (daily, 03:17, without overlapping) deletes older entries in chunks of
  1000 (500 chunks per run at most) and writes a `purge` entry.

## Screens (Activity module)

| URL | Resource / permissions | |
|---|---|---|
| `backend/activity` | `activity-log.list`, `.show`, `.export` | every entry; filters by user, type, module, action and date range; search in summary, resource and record; CSV export |
| `backend/activity/mine` | `activity-mine.list`, `.show` (every group) | the user's own entries (`where('user_id', me)`); another user's entry is "not found" |

The details page shows the before/after changes as a table. IP addresses and user agents are
hidden everywhere (list, details, search, filters, export) from users without
`activity-log.network`. Administrators get `activity-log.*`.

## Table `activity_log`

`id`, `type` (`activity`/`security`), `user_id`, `module`, `resource`, `action`, `record_id`,
`summary`, `changes` (JSON), `ip_address`, `user_agent`, `created_at`; indexes on `created_at`,
`(user_id, created_at)`, `(type, created_at)`, `(module, action)`.
