<?php

declare(strict_types=1);

namespace Modules\Pages\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Controllers\FrontendController;
use Modules\Pages\Services\SitemapBuilder;

/**
 * `GET sitemap.xml`: the XML sitemap (sitemaps.org 0.9) of the public pages (SitemapBuilder).
 * Rate limited like the feeds.
 */
final class Sitemap extends FrontendController
{
    public function index(): ResponseInterface
    {
        return $this->response
            ->setContentType('application/xml', 'UTF-8')
            ->setHeader('X-Robots-Tag', 'noindex')
            ->setBody((new SitemapBuilder())->xml());
    }
}
