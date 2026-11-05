<?php

declare(strict_types=1);

namespace Modules\Api\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\App;

/**
 * Language of API responses (`apilocale`, on `api/*` in app/Config/Filters.php): the request's
 * `Accept-Language` among `Config\App::$supportedLocales` (ca, es, en), so problem+json titles,
 * validation messages and translated fields follow the client; without the header, the
 * application's default. The API has no session, so the web's language choice never applies.
 */
final class ApiLocale implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest || $request->getHeaderLine('Accept-Language') === '') {
            return null;
        }

        $locale = $request->negotiate('language', config(App::class)->supportedLocales);

        if ($locale !== '') {
            $request->setLocale($locale);
            service('language')->setLocale($locale);
        }

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
