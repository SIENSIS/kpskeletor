<?php

declare(strict_types=1);

namespace Modules\Core\Widgets;

/**
 * A dashboard widget (docs/widgets.md). Declared in a module's manifest (`$widgets`), collected
 * by `WidgetRegistry` from the enabled modules and shown only when the user holds its permission
 * **and** it is enabled for one of the user's groups (Dashboard › Group layouts).
 *
 * Extend `Widget` (server-side HTML) or `AsyncWidget` (loaded after the page, cached per user)
 * instead of implementing this interface from scratch.
 */
interface WidgetInterface
{
    /**
     * Stable identifier "<module>.<name>" in lower case (e.g. `core.quick-links`): it is stored in
     * the group layouts and the user layouts and appears in the async data URL.
     */
    public function id(): string;

    /** Translated title of the card (from the language files). */
    public function title(): string;

    /** Bootstrap Icons class of the card header (e.g. `bi bi-link-45deg`). */
    public function icon(): string;

    /**
     * Permission needed to see the widget (a declared `<module>-<resource>.<operation>`), or null
     * when the dashboard's own permission is enough (the widget must then show only data every
     * user may see, or filter it per user itself).
     */
    public function permission(): ?string;

    /**
     * Allowed sizes (`WidgetSizes::ALL`); the first one is the default.
     *
     * @return non-empty-list<string>
     */
    public function sizes(): array;

    /**
     * Options an administrator sets per group (`WidgetOptions`): key => definition with `type`
     * (`int`, `bool`, `string`, `select`, `menu`), `default`, `label` (language key), optional
     * `help`, `min`/`max` (int) and `options` (select: value => language key).
     *
     * @return array<string, array<string, mixed>>
     */
    public function settings(): array;

    /**
     * The body of the card (HTML, already escaped). For an `AsyncWidgetInterface` widget, the
     * placeholder shown while its data loads.
     *
     * @param array<string, mixed> $options Validated options of the user's group layout
     */
    public function render(array $options): string;
}
