<?php

declare(strict_types=1);

namespace Modules\Core\Widgets;

/**
 * Base class of a widget that loads after the page (slow queries, charts drawn in the browser).
 * Subclasses give `data()` and either `renderData()` (HTML from the data; the default component
 * `core/ui/widget` puts it in the card) or their own `component()` that draws the data.
 */
abstract class AsyncWidget extends Widget implements AsyncWidgetInterface
{
    /** The placeholder shown while the data loads. */
    public function render(array $options): string
    {
        return $this->view('Modules\Core\Views\widgets\loading');
    }

    public function renderData(array $data, array $options): string
    {
        return '';
    }

    public function component(): string
    {
        return 'core/ui/widget';
    }

    public function cacheSeconds(): int
    {
        return 300;
    }

    public function refreshSeconds(): int
    {
        return 0;
    }
}
