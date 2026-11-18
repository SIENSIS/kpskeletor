# Tutorial 8 — A new setting

The overdue reminder of tutorial 7 waits a number of **days of grace** that administrators choose
in the Settings screen. You will declare a setting, validate it, read it, and see the form the
Settings module generates for it.

Finished code: `$settings` in `modules/Tasks/Config/Module.php`, read in
`modules/Tasks/Tasks/OverdueReminder.php`; texts `Tasks.settings.*` and `Tasks.settingGroups.*`.
Reference: [`settings.md`](../settings.md).

## 1. Declare it

```php
/** Settings (tutorial 8): Settings › Tasks; read with `setting('Tasks.<key>')`. */
public array $settings = [
    'reminderGraceDays' => [
        'type'    => 'int',
        'default' => 0,
        'rules'   => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[30]',
        'label'   => 'Tasks.settings.reminderGraceDays',
        'help'    => 'Tasks.settings.reminderGraceDaysHelp',
        'group'   => 'reminders',
    ],
    'publicRequests' => [
        'type'    => 'bool',
        'default' => true,
        'label'   => 'Tasks.settings.publicRequests',
        'help'    => 'Tasks.settings.publicRequestsHelp',
        'group'   => 'public',
    ],
];
```

- **`type`**: `string`, `text`, `int`, `float`, `bool`, `select`, `email`, `url`, `color`,
  `json`, `list`, also `multiselect` and `translated` ([`settings.md`](../settings.md#declaring-a-setting)). The
  value is cast to it when read.
- **`rules`**: CodeIgniter validation rules, checked when the form is saved; the type adds its
  own (`integer` here). The form refuses the whole save on any error.
- **`label`/`help`**: language keys (ca, es, en).
- **`group`**: a heading inside the module's tab; its label is `Tasks.settingGroups.<group>`.
- A `group` of `security` or `security.<sub>` would move the setting to Settings › Security
  (permission `settings.security` and re-authentication). Use it for anything that can weaken
  security, and refuse weak values with rules (`Modules\Core\Settings\SecurityRules`), never with
  code in a controller.

Add the texts:

```php
'settings' => [
    'reminderGraceDays'     => 'Days of grace before the overdue reminder',
    'reminderGraceDaysHelp' => 'The daily reminder is sent when a to-do task is this many days past its due date (0: from the day after).',
    // …
],
'settingGroups' => [
    'reminders' => 'Reminders',
    'public'    => 'Public site',
],
```

Run `php spark modules:sync`: the setting is registered, and the Core adds the permission
`settings.tasks` (save the Tasks tab), granted to administrators.

## 2. Read it

```php
$grace = max(0, min(30, (int) setting('Tasks.reminderGraceDays')));
```

`setting('<Module>.<key>')` returns the stored value or the manifest's default. Clamp it where you
use it anyway: a value stored before the rules changed, or set from code, must not break the task.
Only values different from the default are stored.

## 3. The Settings screen

Open Settings › **Tasks** (`backend/settings/tasks`): the form is generated from the manifest —
the "Reminders" heading, a number input with its help, and the "Public site" switch. A user with
`settings.update` but without `settings.tasks` sees it read-only; a forged POST gets a 403. A
value of 99 is refused with the rule's message (422) and nothing is stored. Every change is
written to the activity log (`settings_changed`, with the values before and after).

## Tests

`modules/Tasks/Tests/WidgetsAndReminderTest.php`:

- `testTheGraceDaysSettingDelaysTheReminder`: `setting()->set('Tasks.reminderGraceDays', 3)`
  delays the reminder; `forget()` restores the default at the end of the test;
- `testTheSettingsAreShownAndValidatedInTheSettingsScreen`: the tab shows both settings and the
  translated group heading; `Tasks-reminderGraceDays=99` is refused (422, nothing stored); `3` is
  saved and read back as an integer.

The Settings screens themselves (permissions per tab, casting, logging) are tested once for every
module in `modules/Settings/Tests/SettingsScreensTest.php`.

```bash
vendor/bin/phpunit modules/Tasks/Tests/WidgetsAndReminderTest.php --filter Setting
```

Next: [tutorial 9](09-child-theme.md), a child theme.
