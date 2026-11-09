<?php

declare(strict_types=1);

namespace Modules\Core\Widgets;

/**
 * A widget that loads after the page: the dashboard prints a placeholder with the component
 * `component()` and the URL of its data (permission-checked, rate limited); the response holds
 * `data()` and, when the widget renders it on the server, `renderData()`. The response is cached
 * per user, language and options for `cacheSeconds()`.
 */
interface AsyncWidgetInterface extends WidgetInterface
{
    /**
     * JSON-serialisable data for the user who asks (`auth()->user()`).
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function data(array $options): array;

    /**
     * HTML built from the data (escaped), or '' when the component draws the data itself.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $options
     */
    public function renderData(array $data, array $options): string;

    /** ES module that loads and shows the data (`core/ui/widget` puts `html` in the card). */
    public function component(): string;

    /** Seconds the response is cached per user (0: never). */
    public function cacheSeconds(): int;

    /** Seconds between automatic refreshes in the browser (0: never). */
    public function refreshSeconds(): int;
}
