# Notifications

Cross-module notifications of the Core (specification "Notifications"; decisions D-11-01 to
D-11-04): a module declares notification types and sends them to users, groups or the holders of
a permission; each user chooses the channels.

## Notification types

A class in the module's `Notifications/` folder that extends
`Modules\Core\Notifications\NotificationType`, declared in the manifest:

```php
// modules/News/Notifications/NewNotice.php
namespace Modules\News\Notifications;

use Modules\Core\Notifications\NotificationMessage;
use Modules\Core\Notifications\NotificationType;

final class NewNotice extends NotificationType
{
    public static function title(): string        // the type in the preferences page
    {
        return lang('News.notifications.newNotice');
    }

    public static function icon(): string
    {
        return 'bi bi-megaphone';
    }

    public function render(): NotificationMessage  // bell, list and email
    {
        return new NotificationMessage(
            lang('News.notifications.newNoticeTitle', [$this->data['title'] ?? '']),
            lang('News.notifications.newNoticeBody'),
            'backend/news/notices/' . ($this->data['id'] ?? ''),
        );
    }
}
```

```php
// modules/News/Config/Module.php
public array $notifications = [\Modules\News\Notifications\NewNotice::class];
```

Then `php spark modules:sync`. Optional overrides:

| Method | Default | |
|---|---|---|
| `description()` | `''` | Help text in the preferences page |
| `icon()` | `bi bi-bell` | Bootstrap Icons class |
| `defaultChannels()` | `['database', 'email']` | Channels when the user has not chosen |
| `mandatory()` | `false` | Always sent through the default channels; users cannot turn it off |
| `emailSubject()` | the rendered title | |
| `emailView()` | `Modules\Core\Views\emails\notification` | Receives `message`, `url`, `subject`, `preferencesUrl`, `user` |

Rules:

- The **data** given to the constructor is stored as JSON and the text is rendered when it is
  shown, in the reader's language. Keep scalar values only, never secrets or personal data the
  recipient may not see.
- The link (`NotificationMessage::$url`) is an **internal path**; absolute and scheme-relative
  URLs are ignored.
- Only types declared by **enabled** modules are sent; others are logged and dropped. Stored
  notifications of a type that no longer exists show "This notification is no longer available".

## Sending

```php
notify($user, new NewNotice(['id' => 7, 'title' => 'Closed on Monday']));   // User, id or a list
notifyGroup('admin', new NewNotice([...]));                                   // or ['admin', 'editor']
notifyPermission('news-read.view', new NewNotice([...]));                  // every holder
```

- They return the number of users reached and never throw: delivery problems are logged.
- `notifyPermission()` checks each user with `Authorization::for($user)` (superadmins included),
  so it works from scheduled tasks, commands and the API, without a session.
- Banned users receive nothing; each user once per call.
- The helpers are autoloaded; the service is `service('notifications')` (`Notifier`).

## Channels

- **`database`**: a row in `notifications` (the bell and the list page).
- **`email`**: rendered in the recipient's language (their profile's language, registered by the
  Auth module through `RecipientLocale`) and **queued** in `mail_queue`; the scheduled task
  "Send the queued notification emails" sends it within a minute ([`scheduler.md`](scheduler.md)), through the
  `email` service and the outbound allow-list for SMTP. Never sent inline in the request.

- **`push`** (session 44, D-44-01, D-44-02): Web Push to the browsers and installed PWAs the user
  turned it on in (see "Push notifications" below). Rendered in the recipient's language and
  **queued** in `push_queue`, one row per device; the scheduled task "Send the queued push
  notifications" encrypts and sends it within a minute. Only while `Core.pushEnabled` is on and
  the VAPID keys are valid; a user without devices receives nothing through it.

`NotificationType::CHANNELS` is `['database', 'email', 'push']`; the default `defaultChannels()`
returns all three (push is opt-in per device anyway). A type that should never reach a lock
screen returns only `database`/`email`.

Security emails (magic links, password resets, 2FA codes, new-device notices) are **not**
notifications: they are sent at once by the Auth module's `SecurityMailer` (D-00-15).

## User preferences

Back office › user menu › **Notifications** › *Notification preferences*
(`backend/profile/notifications`): one row per type of the enabled modules, grouped by module,
with a checkbox per channel. Mandatory types are shown checked and cannot be changed (the server
ignores them too). No stored choice means the type's default channels. The **On my devices**
(`push`) column is shown only while push works for the site; saving the form while it is hidden
keeps the stored push choices (`Notifier::savePreferences(..., $editable)`). The migration that
added the channel gave `push` to every stored preference that had at least one channel.

## Push notifications

Standard Web Push (RFC 8030) with VAPID: free, no provider account, implemented in the Core with
PHP's OpenSSL only (D-44-01) and sent through `OutboundClient`.

| Piece | |
|---|---|
| `app/Config/Push.php` | `.env` keys `push.vapidPublicKey`, `push.vapidPrivateKey`, `push.subject` (`mailto:` or `https:`); `$serviceHosts`; `ttl` (1 day), `maxAttempts` (5), `maxFailures` (10), `maxDevices` (20 per user), `batch` (100), `keepDays` (7) |
| Settings | `Core.pushEnabled` (off) and `Core.pushContent` (`full` \| `generic`) in Settings › Security › Notifications |
| `Modules\Core\Push\PayloadEncryption` | RFC 8291 `aes128gcm` (ECDH P-256, HKDF-SHA256, AES-128-GCM, one record), checked against the RFC's appendix A vectors |
| `Modules\Core\Push\Vapid` | RFC 8292 JWT ES256 (`aud` = the endpoint's origin, `exp` 12 h, `sub`), `Authorization: vapid t=…, k=…` |
| `Modules\Core\Push\PushSubscriptions` | `push_subscriptions` (user, endpoint + SHA-256, `p256dh`, `auth`, label, failures, created, last used); every read and change takes the user id |
| `Modules\Core\Push\PushQueue`, `PushSender` | `push_queue` (subscription, JSON payload before encryption, urgency, attempts, next attempt, status); 2xx sent, 404/410 device deleted, 429/5xx/network retried with backoff, other answers fail and count a failure (a device with `maxFailures` in a row is deleted) |
| `Modules\Core\Notifications\Channels\PushChannel` | Renders the payload (`title`, `body` ≤ 300 characters, internal `url`, `icon`, a `tag` per type) in the recipient's language; `Urgency: high` for mandatory types; `generic` sends the site name and "You have a new notification" with a link to the notifications page |
| `Modules\Core\Tasks\SendQueuedPush` | Every minute; nothing is sent while push is off (the queue waits); old rows are deleted |
| `php spark push:keys [--force] [--yes]` | Generates (or rotates, removing every subscription) the keys; `app:install` writes them |

Routes (personal, `can:core-notifications.list`, CSRF, `ratelimit:forms` on the POSTs):

| URL | |
|---|---|
| `GET backend/profile/push` | JSON: `{enabled, publicKey, devices: [{id, label, created, lastUsed, hash}]}` (never endpoints or keys; `hash` is the SHA-256 of the endpoint, for "this device") |
| `POST backend/profile/push/subscribe` | JSON `PushSubscription.toJSON()`; 422 with the reason when refused, 404 while push is off |
| `POST backend/profile/push/delete/{id}`, `test/{id}`, `label/{id}` | Own devices only (404 otherwise); JSON for scripts, a redirect back for forms |

Subscriptions are accepted only with an `https://` endpoint on a host of
`Config\Push::$serviceHosts` (never an IP address or a local name; D-44-02); those hosts join the
outbound allow-list while push is on, and `OutboundClient` checks the resolved addresses on every
delivery. A browser subscribed before by another account moves to the new user and its queued
messages are dropped. Deleted users lose their devices when the queue meets them.

Front end: `core/push.js` (Profile › Notifications; the logout question through
`push_logout_attributes()` on the theme's logout form) and the service worker (`/sw.js`,
[`frontend.md`](frontend.md)).

To try it on a phone the site must be on HTTPS with a valid certificate; iPhone and iPad receive
Web Push only from the site added to the home screen (iOS/iPadOS 16.4+), so turn the PWA on too.

## Screens

All of them need `core-notifications.list` (granted to every group, D-00-17) and show only the
user's own notifications.

| URL | |
|---|---|
| top bar bell | Unread count; the latest 8 when opened (`core/ui/bell`, refreshed every 2 minutes); "Mark all as read"; links to the list and the preferences |
| `backend/notifications` | The list (kpcrudy, `where(user_id)`): show, delete (`core-notifications.delete`), mark one / selected / all as read |
| `backend/notifications/bell` | JSON for the bell: `{unread, items: [{id, title, body, icon, url, date, read}]}` |
| `backend/notifications/open/{id}` | Marks it as read and goes to its link (or its detail page) |
| `POST backend/notifications/read-all` | Marks everything as read (JSON for the bell, otherwise back to the list) |
| `backend/profile/notifications` | Preferences (GET form, POST save) |

The API ([`api.md`](api.md)): `GET api/v1/notifications[/{id}]` (the user's own, rendered in
the request's language), `GET api/v1/notifications/unread`, `POST api/v1/notifications/{id}/read`
and `POST api/v1/notifications/read-all` (`core-notifications.read`, every group, D-26-01);
`Modules\Core\Controllers\Api\Notifications` on top of the same table as `Inbox`.

## Notification types of the skeleton

| Type | Module | Sent to | |
|---|---|---|---|
| `ScheduledTaskFailed` | Core | holders of `schedule.list` | A scheduled task failed |
| `SecurityCheckFailed` | Core | the `superadmin` group | A security check turned red ([`security.md`](security.md)) |
| `PackageProcessed` | Core | the user who approved the package | A staged module or theme package was processed ([`installing-modules.md`](installing-modules.md)) |
| `TwoFactorReset` | Auth | the user | Mandatory: an administrator reset their 2FA |
| `BackupResult` | Backup | holders of `backup.list` | Result of the scheduled backup ([`backup-restore.md`](backup-restore.md)) |
| `NewVersion` | Updates | holders of `updates.view` | A new release is available ([`updates.md`](updates.md)) |
| `NewNotice` | News | the notice's groups, or holders of `news-read.view` for private notices | A notice was published |
| `EventReminder` | Events | the user who asked for the reminder | An event is about to start |
| `BirthdayGreeting` | Users | the user | Birthday greeting (default channel `database` only) |
| `TaskAssigned`, `TaskOverdue`, `TaskRequested` | Tasks | assignees; holders of `tasks-requests.list` | Tutorial module `Tasks` |

## Testing

`modules/Core/Tests/NotifierTest.php` shows the patterns: declare test types with
`Tests\Support\Scheduler\DeclaresFixtures`, call `notify()`, check the `notifications` and
`mail_queue` rows, run `SendQueuedEmails` against CodeIgniter's `MockEmail` (set
`returnValue = false` to simulate failures). Push: `PushCryptoTest` (RFC vectors),
`PushDeliveryTest` (queue, `FakePushTransport` through the real `OutboundClient`, decrypting with
the fake browser's keys from `PushFixturesTrait`), `PushSubscriptionsTest` (routes, SSRF
refusals), `PushKeysTest`.
