<?php

declare(strict_types=1);

namespace Modules\Core\Widgets;

/**
 * Base class of a server-side widget: medium by default, no options, the dashboard's permission.
 * Subclasses give `id()`, `title()` and `render()` and usually `permission()`.
 *
 * ```php
 * final class MyPendingTasks extends Widget
 * {
 *     public function id(): string { return 'tasks.pending'; }
 *     public function title(): string { return lang('Tasks.widget.pending'); }
 *     public function permission(): ?string { return 'tasks-tasks.list'; }
 *     public function render(array $options): string
 *     {
 *         return $this->view('Modules\Tasks\Views\widgets\pending', ['items' => …]);
 *     }
 * }
 * ```
 */
abstract class Widget implements WidgetInterface
{
    public function icon(): string
    {
        return 'bi bi-grid';
    }

    public function permission(): ?string
    {
        return null;
    }

    public function sizes(): array
    {
        return [WidgetSizes::MEDIUM, WidgetSizes::SMALL, WidgetSizes::LARGE, WidgetSizes::FULL];
    }

    public function settings(): array
    {
        return [];
    }

    /**
     * Renders a view through the theme (a theme can override it in
     * `Views/overrides/<Module>/…`).
     *
     * @param array<string, mixed> $data
     */
    protected function view(string $view, array $data = []): string
    {
        return theme_view($view, $data);
    }

    /**
     * The "nothing to show" message of a widget body.
     */
    protected function empty(string $message, string $icon = 'bi bi-inbox'): string
    {
        return theme_component('empty_state', ['title' => $message, 'icon' => $icon, 'compact' => true]);
    }
}
