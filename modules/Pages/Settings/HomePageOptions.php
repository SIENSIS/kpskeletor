<?php

declare(strict_types=1);

namespace Modules\Pages\Settings;

use Modules\Core\Settings\SettingOptionsProvider;
use Modules\Pages\Services\PageTree;

/**
 * Choices of the setting `Pages.homePage` (D-20-01): "the theme's home" and every page in tree
 * order ("— — Child"), by id. Drafts are offered too (a home page can be prepared before it is
 * published); `/` only shows a page that is published and readable by the visitor.
 */
final class HomePageOptions implements SettingOptionsProvider
{
    public function options(): array
    {
        $options = ['' => lang('Pages.settings.homePageNone')];

        foreach ((new PageTree())->options(null, service('request')->getLocale()) as $id => $title) {
            $options[(string) $id] = $title;
        }

        return $options;
    }
}
