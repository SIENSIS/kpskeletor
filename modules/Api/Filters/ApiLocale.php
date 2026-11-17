<?php

declare(strict_types=1);

namespace Modules\Api\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\I18n\LocaleNegotiator;

/**
 * Language of API responses (`apilocale`, on `api/*` in app/Config/Filters.php): the request's
 * `Accept-Language` among the site's available locales (Modules\Core\I18n\LocaleNegotiator), so problem+json titles,
 * validation messages and translated fields follow the client; without the header, the
 * site's default (`Core.defaultLocale`). The API has no session, so the web's language choice never applies.
 */
final class ApiLocale implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            return null;
        }

        $locale = (new LocaleNegotiator())->negotiate(null, $request->getHeaderLine('Accept-Language'));
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
