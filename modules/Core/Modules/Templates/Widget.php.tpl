<?php

declare(strict_types=1);

namespace Modules\{{Name}}\Widgets;

/**
 * Dashboard widget of the {{Name}} module (stub). It implements the Core's WidgetInterface once
 * the dashboard exists.
 */
final class {{Name}}Widget
{
    public function title(): string
    {
        return lang('{{Name}}.widget.title');
    }

    public function render(): string
    {
        return '';
    }
}
