<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\RedirectResponse;
use Modules\Core\I18n\LocaleNegotiator;
use Modules\Core\I18n\Locales;

/**
 * The public language switch (`POST language`, docs/i18n.md "Choosing the language"): stores the
 * visitor's choice among the site's available locales in the `kp_locale` cookie, which the
 * global `locale` filter reads before the browser's `Accept-Language`, and tells the modules
 * through the event `localeChosen` (Auth saves it as the signed-in user's profile language).
 * Then back to the page the form came from (internal paths only).
 */
final class LanguageSwitch extends Controller
{
    /** Event triggered with (int $userId, string $locale) when a signed-in user chooses a language. */
    public const EVENT = 'localeChosen';

    public function set(): RedirectResponse
    {
        helper('redirect');

        $locale   = (string) $this->request->getPost('locale');
        $back     = $this->request->getPost('return');
        $response = safe_redirect(is_string($back) ? $back : null);

        if (! Locales::isAvailable($locale)) {
            return $response;
        }

        $response->setCookie(LocaleNegotiator::COOKIE, $locale, 365 * DAY, httponly: true);

        $userId = function_exists('auth') && auth()->loggedIn() ? (int) auth()->id() : null;

        if ($userId !== null) {
            Events::trigger(self::EVENT, $userId, $locale);
        }

        return $response;
    }
}
