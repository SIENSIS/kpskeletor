<?php

declare(strict_types=1);

namespace Modules\Core\Config;

use CodeIgniter\Config\BaseService;
use Modules\Core\Activity\ActivityLogger;
use Modules\Core\Calendar\CalendarPublisher;
use Modules\Core\Calendar\NullCalendar;
use Modules\Core\Frontend\AssetManifest;
use Modules\Core\Install\InstallState;
use Modules\Core\Notifications\Notifier;
use Modules\Core\Storage\Storage;
use Modules\Core\Theme\ThemeManager;

/**
 * Core services (discovered by CodeIgniter through the module namespace).
 */
class Services extends BaseService
{
    /**
     * The themes of the current request: active theme per area, view resolution, options,
     * regions and assets.
     */
    public static function theme(bool $getShared = true): ThemeManager
    {
        if ($getShared) {
            return static::getSharedInstance('theme');
        }

        return new ThemeManager();
    }

    /**
     * The asset manifest (published URLs and hashes); refreshes stale assets in development.
     */
    public static function assets(bool $getShared = true): AssetManifest
    {
        if ($getShared) {
            return static::getSharedInstance('assets');
        }

        return AssetManifest::create();
    }

    /**
     * Notifications: `notify()`, `notifyGroup()`, `notifyPermission()` (docs/notifications.md).
     */
    public static function notifications(bool $getShared = true): Notifier
    {
        if ($getShared) {
            return static::getSharedInstance('notifications');
        }

        return new Notifier();
    }

    /**
     * The activity and security log: `activity_log()`, `security_log()` (docs/activity.md).
     */
    public static function activity(bool $getShared = true): ActivityLogger
    {
        if ($getShared) {
            return static::getSharedInstance('activity');
        }

        return new ActivityLogger();
    }

    /**
     * File storage: the `public` and `private` disks and the upload rules (docs/storage.md).
     */
    public static function storage(bool $getShared = true): Storage
    {
        if ($getShared) {
            return static::getSharedInstance('storage');
        }

        return new Storage(config(\Config\Storage::class));
    }

    /**
     * Whether the application is configured (`.env` or `writable/install.lock`). In the testing
     * environment the suite runs without either, so it counts as configured; tests of the
     * installer inject an InstallState on a temporary folder.
     */
    public static function installstate(bool $getShared = true): InstallState
    {
        if ($getShared) {
            return static::getSharedInstance('installstate');
        }

        return new InstallState(assumeConfigured: ENVIRONMENT === 'testing');
    }

    /**
     * The calendar API (`publish()` / `unpublish()`, docs/calendar-api.md): the class of
     * `Config\Calendars::$publisher` (the Events module's service), else a `NullCalendar`.
     */
    public static function calendar(bool $getShared = true): CalendarPublisher
    {
        if ($getShared) {
            return static::getSharedInstance('calendar');
        }

        $class = config(Calendars::class)->publisher;

        return is_a($class, CalendarPublisher::class, true) ? new $class() : new NullCalendar();
    }
}
