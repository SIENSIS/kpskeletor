<?php

declare(strict_types=1);

namespace Modules\Core\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * After filter (global, `csrftoken`): answers a script's unsafe request (`X-Requested-With:
 * XMLHttpRequest`, not GET/HEAD/OPTIONS) with the current CSRF token in the CSRF header.
 * `Config\Security::$regenerate` changes the token after each verified POST; `core/http.js`
 * (`refreshCsrf()`) then updates the page's meta tag and the hidden fields of its forms, so a
 * form submitted after a script's request (e.g. after a file-picker upload) or a second script
 * request still carries a valid token. Same-origin responses only (the token is the caller's own).
 */
final class CsrfTokenHeader implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (! $request instanceof IncomingRequest || ! $request->isAJAX()
            || in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return null;
        }

        $response->setHeader(csrf_header(), csrf_hash());

        return $response;
    }
}
