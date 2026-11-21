<?php

declare(strict_types=1);

namespace Modules\News\Services;

use Modules\Core\Sections\SectionLinks;
use Throwable;

/**
 * The page marked as the main news list (D-46-03, setting `News.listPage`, a page id): when it is
 * set and the page is published, `GET news` answers a 301 redirect to it (in the visitor's
 * language). The page's URL comes from the link type `pages.page` of the Core's link registry
 * (`$menuLinks` of the Pages module), so News does not depend on Pages: with Pages disabled, or
 * the page gone or unpublished, nothing changes.
 */
final class ListPage
{
    /** The link type of a page (Pages module). */
    public const LINK_TYPE = 'pages.page';

    public function id(): ?int
    {
        try {
            $value = (string) setting('News.listPage');
        } catch (Throwable) {
            return null;
        }

        return ctype_digit($value) && $value !== '0' ? (int) $value : null;
    }

    /** The public address of the page, or null (not set, gone, not published, Pages disabled). */
    public function url(): ?string
    {
        $id = $this->id();

        return $id === null ? null : (new SectionLinks())->href(self::LINK_TYPE, (string) $id);
    }

    /**
     * The pages that can be chosen: id => title (tree order), or [] without the Pages module.
     *
     * @return array<int|string, string>
     */
    public function options(): array
    {
        $type = (new SectionLinks())->types()[self::LINK_TYPE] ?? null;

        try {
            return $type?->options() ?? [];
        } catch (Throwable) {
            return [];
        }
    }
}
