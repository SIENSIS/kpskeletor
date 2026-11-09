<?php

declare(strict_types=1);

namespace Modules\{{Name}}\Widgets;

use Modules\Core\Widgets\Widget;

/**
 * Dashboard widget of the {{Name}} module (stub; docs/widgets.md). It appears in Dashboard ›
 * Group layouts after `php spark modules:sync` and is shown to users who hold its permission.
 */
final class {{Name}}Widget extends Widget
{
    public function id(): string
    {
        return '{{name}}.main';
    }

    public function title(): string
    {
        return lang('{{Name}}.widget.title');
    }

    public function permission(): string
    {
        return '{{name}}-main.view';
    }

    public function render(array $options): string
    {
        return $this->empty(lang('{{Name}}.widget.title'));
    }
}
