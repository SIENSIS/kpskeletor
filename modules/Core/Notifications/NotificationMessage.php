<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Modules\Core\Routing\BackendPath;

/**
 * What a notification says: a title, an optional body and an optional internal link (a path
 * such as "backend/news/notices/7", written with `backend_link()`; external URLs are ignored).
 */
final class NotificationMessage
{
    public function __construct(
        public readonly string $title,
        public readonly string $body = '',
        public readonly ?string $url = null,
    ) {
    }

    /**
     * The internal path of the link (a stored back-office link moved under the installed
     * prefix), or null (absolute URLs, scheme-relative URLs and
     * backslashes are refused: notifications only link inside the site).
     */
    public function path(): ?string
    {
        $url = trim((string) $this->url);

        if ($url === '' || preg_match('#^[a-z][a-z0-9+.-]*:|^//|\\\\#i', $url) === 1) {
            return null;
        }

        // Stored back-office links use the canonical prefix (D-40-02).
        return BackendPath::resolve(ltrim($url, '/'));
    }
}
