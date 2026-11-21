<?php

declare(strict_types=1);

namespace Modules\News\Settings;

use Modules\Core\Settings\SettingOptionsProvider;
use Modules\News\Services\ListPage;

/**
 * Choices of the setting `News.listPage` (D-46-03): "none" (the module's own `/news` list) and the
 * site's pages (tree order, by id) through the link type `pages.page`.
 */
final class ListPageOptions implements SettingOptionsProvider
{
    public function options(): array
    {
        $options = ['' => lang('News.settings.listPageNone')];

        foreach ((new ListPage())->options() as $id => $title) {
            $options[(string) $id] = $title;
        }

        return $options;
    }
}
