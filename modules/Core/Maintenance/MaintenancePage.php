<?php

declare(strict_types=1);

namespace Modules\Core\Maintenance;

use CodeIgniter\Settings\Settings;
use Config\Services;
use Throwable;

/**
 * The 503 page of maintenance mode, drawn by the theme like the 403/404/500 pages: the standard
 * view `errors/maintenance` (Core default `modules/Core/Views/errors/maintenance.php`; a theme
 * overrides it in `themes/<slug>/Views/errors/maintenance.php`, D-28-07).
 *
 * The database may be being replaced (a restore), so nothing may read it: before rendering, the
 * request's settings service is swapped for one without handlers, so every `setting()` answers
 * the file configuration (`Config\Theme`, `Config\Core`…): the public theme of `Config\Theme`,
 * its default options and colours, the default site name. If the theme cannot render, a plain
 * page without styles is returned instead (a broken theme never hides the maintenance notice).
 */
final class MaintenancePage
{
    /**
     * @param string $message the administrator's plain-text message (empty = the default text)
     */
    public static function render(string $message = ''): string
    {
        try {
            $config           = clone config('Settings');
            $config->handlers = [];
            Services::injectMock('settings', new Settings($config));
            Services::resetSingle('theme');

            $theme = service('theme');
            $theme->setArea('public');

            return $theme->render('errors/maintenance', [
                'message' => $message,
                'title'   => lang('Core.maintenance.title'),
                'retry'   => current_url(),
            ], ['saveData' => false]);
        } catch (Throwable) {
            return self::plain($message);
        }
    }

    /**
     * Minimal page without theme, styles or scripts.
     */
    public static function plain(string $message = ''): string
    {
        $title = esc(lang('Core.maintenance.title'));
        $text  = esc($message !== '' ? $message : lang('Core.maintenance.message'));

        return '<!doctype html><html lang="' . esc(service('request')->getLocale()) . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
            . '<title>' . $title . '</title></head><body><main><h1>' . $title . '</h1><p>' . $text . '</p></main></body></html>';
    }
}
