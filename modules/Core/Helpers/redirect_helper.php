<?php

declare(strict_types=1);

use CodeIgniter\HTTP\RedirectResponse;
use Modules\Core\Security\SafeRedirect;

if (! function_exists('safe_redirect')) {
    /**
     * A redirect to a URL that came from the request (`return`, `next`…) only when it is internal
     * (a path, or an http(s) URL of this site's hosts); otherwise to the fallback.
     *
     * ```php
     * return safe_redirect($this->request->getGet('return'), 'backend');
     * ```
     */
    function safe_redirect(?string $url, string $fallback = '/'): RedirectResponse
    {
        $target = (new SafeRedirect())->target($url, $fallback);

        if (SafeRedirect::isInternalPath($target)) {
            $target = site_url(ltrim($target, '/'));
        }

        return redirect()->to($target);
    }
}
