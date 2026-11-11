<?php

declare(strict_types=1);

namespace Modules\Core\Settings;

/**
 * Choices of a `select` setting computed at run time (e.g. the pages of the site), declared in a
 * manifest with `'optionsProvider' => MyOptions::class` instead of fixed `options` (D-20-01).
 * The Settings form offers them and only accepts one of them.
 */
interface SettingOptionsProvider
{
    /**
     * value => label already translated (the label is shown as is, never as a language key).
     *
     * @return array<string, string>
     */
    public function options(): array;
}
