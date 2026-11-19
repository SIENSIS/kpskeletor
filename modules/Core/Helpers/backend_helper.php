<?php

declare(strict_types=1);

use Modules\Core\Routing\BackendPath;

if (! function_exists('backend_path')) {
    /**
     * A back-office path under the installed prefix (`app.backendPath`, default `backend`):
     * `backend_path()` = `admin`, `backend_path('news/posts')` = `admin/news/posts`.
     */
    function backend_path(string $path = ''): string
    {
        return BackendPath::path($path);
    }
}

if (! function_exists('backend_url')) {
    /**
     * The absolute URL of a back-office path: `backend_url('news')` = `https://…/admin/news`.
     */
    function backend_url(string $path = ''): string
    {
        return BackendPath::url($path);
    }
}

if (! function_exists('backend_link')) {
    /**
     * The stored form of a back-office link (notification links, quick links, menu links):
     * relative to the back office, written with the canonical prefix `backend`, resolved when
     * shown, so a later change of the prefix does not break it.
     */
    function backend_link(string $path = ''): string
    {
        return BackendPath::link($path);
    }
}
