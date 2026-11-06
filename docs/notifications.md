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
notifyPermission('news-notices.list', new NewNotice([...]));                  // every holder
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
  "Send the queued notification emails" sends it within a minute (docs/scheduler.md), through the
  `email` service and the outbound allow-list for SMTP. Never sent inline in the request.

Security emails (magic links, password resets, 2FA codes, new-device notices) are **not**
notifications: they are sent at once by the Auth module's `SecurityMailer` (D-00-15).

## User preferences

Back office › user menu › **Notifications** › *Notification preferences*
(`backend/profile/notifications`): one row per type of the enabled modules, grouped by module,
with a checkbox per channel. Mandatory types are shown checked and cannot be changed (the server
ignores them too). No stored choice means the type's default channels.

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

The API endpoints (`api/v1/notifications`) arrive with the Core API (session 26).

## Notification types of the skeleton

| Type | Module | Sent to | |
|---|---|---|---|
| `ScheduledTaskFailed` | Core | holders of `schedule.list` | A scheduled task failed |
| `TwoFactorReset` | Auth | the user | Mandatory: an administrator reset their 2FA |

## Testing

`modules/Core/Tests/NotifierTest.php` shows the patterns: declare test types with
`Tests\Support\Scheduler\DeclaresFixtures`, call `notify()`, check the `notifications` and
`mail_queue` rows, run `SendQueuedEmails` against CodeIgniter's `MockEmail` (set
`returnValue = false` to simulate failures).
