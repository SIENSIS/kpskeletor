<?php

declare(strict_types=1);

namespace Modules\Core\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\I18n\LocaleNegotiator;

/**
 * Global filter `locale` (before `authsession`): sets the request's and the language service's
 * locale from the language chosen with the public switch (cookie `kp_locale`), else the browser
 * (`Accept-Language`), else the site's default, among the available locales. For a logged-in user `authsession` then applies the profile's language; the API
 * negotiates its own (`apilocale`).
 */
final class Locale implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            return null;
        }

        $chosen = $request->getCookie(LocaleNegotiator::COOKIE);
        $locale = (new LocaleNegotiator())->negotiate(is_string($chosen) ? $chosen : null, $request->getHeaderLine('Accept-Language'));
        $request->setLocale($locale);
        service('language')->setLocale($locale);

        return null;
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
